<?php

namespace App\Modules\Production\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Service\EquipmentService;
use App\Modules\Organization\Model\Employee;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Facades\DB;

class ProductionReportService
{
    public function __construct(
        private readonly EquipmentService $equipments,
        private readonly OperationLogService $operationLogs,
    ) {}

    public const array REPORTABLE_ORDER_STATUSES = [
        ProductionOrder::RELEASED,
        ProductionOrder::IN_PROGRESS,
        ProductionOrder::PAUSED,
    ];

    /**
     * The task row lock serialises concurrent reports, so two requests cannot
     * both read the same remainder and together overshoot the planned quantity.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function create(array $data): ProductionReport
    {
        return DB::transaction(function () use ($data): ProductionReport {
            $task = ProductionTask::query()->lockForUpdate()->findOrFail($data['production_task_id']);
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($task->production_order_id);
            $this->ensureReportable($task, $order);

            $operation = RouteOperation::query()->findOrFail($data['route_operation_id']);

            if ($operation->route_id !== $task->route_id) {
                throw new ApiException('工序不属于该生产任务的工艺路线', 422);
            }

            $employee = Employee::query()->whereKey($data['employee_id'])->where('status', true)->first();

            if ($employee === null) {
                throw new ApiException('作业人员不存在或已停用，不能报工', 422);
            }

            $equipmentId = $data['equipment_id'] ?? null;

            if ($equipmentId !== null) {
                $lineId = $task->production_line_id ?? $order->production_line_id;
                $equipment = $this->equipments->available(
                    (int) $equipmentId,
                    $lineId,
                    '设备不存在或当前不可用，不能报工',
                );
                $equipmentId = $equipment->id;
            }

            $quantity = $this->milli($data['quantity']);
            $qualified = $this->milli($data['qualified_quantity']);
            $defective = $this->milli($data['defective_quantity']);

            if ($qualified + $defective !== $quantity) {
                throw new ApiException('合格数 + 不良数必须等于报工数', 422);
            }

            $remaining = $this->milli($task->planned_quantity) - $this->milli($task->produced_quantity);

            if ($quantity > $remaining) {
                throw new ApiException('报工数量不能超过任务剩余数量', 422);
            }

            $sequence = ProductionReport::query()->where('production_task_id', $task->id)->count() + 1;

            $report = ProductionReport::query()->create([
                'code' => ProductionReport::codeFor($task->code, $sequence),
                'production_task_id' => $task->id,
                'route_operation_id' => $operation->id,
                'employee_id' => $employee->id,
                'team_id' => $employee->team_id,
                'equipment_id' => $equipmentId,
                'quantity' => $this->decimal($quantity),
                'qualified_quantity' => $this->decimal($qualified),
                'defective_quantity' => $this->decimal($defective),
                'type' => $data['type'] ?? ProductionReport::NORMAL,
                'reported_at' => $data['reported_at'] ?? now(),
            ]);

            $producedBefore = $task->produced_quantity;
            $this->shiftQuantities($task, $order, $quantity, $qualified, $defective);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.report.create',
                actionName: '生产报工',
                objectType: '生产报工',
                objectId: $report->id,
                objectName: $report->code,
                before: ['任务累计产量' => $producedBefore],
                after: ['任务累计产量' => $task->produced_quantity],
            ));

            return $report;
        });
    }

    /**
     * Reports stay in place for the audit trail; reversing flips the type and
     * rolls the same quantities back off the task and order.
     *
     * @throws ApiException
     */
    public function reverse(ProductionReport $report): ProductionReport
    {
        return DB::transaction(function () use ($report): ProductionReport {
            $locked = ProductionReport::query()->lockForUpdate()->findOrFail($report->getKey());

            if ($locked->type === ProductionReport::REVERSED) {
                throw new ApiException('报工已冲销，不能重复冲销', 422);
            }

            $task = ProductionTask::query()->lockForUpdate()->findOrFail($locked->production_task_id);
            $order = ProductionOrder::query()->lockForUpdate()->findOrFail($task->production_order_id);
            $typeBefore = $locked->typeLabel();
            $producedBefore = $task->produced_quantity;

            $locked->update([
                'type' => ProductionReport::REVERSED,
                'reversed_at' => now(),
            ]);

            $this->shiftQuantities(
                $task,
                $order,
                -$this->milli($locked->quantity),
                -$this->milli($locked->qualified_quantity),
                -$this->milli($locked->defective_quantity),
            );

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.report.reverse',
                actionName: '冲销报工',
                objectType: '生产报工',
                objectId: $locked->id,
                objectName: $locked->code,
                before: ['报工状态' => $typeBefore, '任务累计产量' => $producedBefore],
                after: ['报工状态' => $locked->typeLabel(), '任务累计产量' => $task->produced_quantity],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    private function ensureReportable(ProductionTask $task, ProductionOrder $order): void
    {
        if (! in_array($order->status, self::REPORTABLE_ORDER_STATUSES, true)
            || $task->status === ProductionTask::COMPLETED) {
            throw new ApiException('只有已下达且未完成的生产任务可以报工', 422);
        }
    }

    /**
     * Comparing in thousandths keeps 0.1 + 0.2 equal to 0.3 instead of a float
     * near-miss.
     */
    private function milli(mixed $value): int
    {
        return (int) round((float) $value * 1000);
    }

    private function decimal(int $milli): string
    {
        return number_format($milli / 1000, 3, '.', '');
    }

    private function shiftQuantities(
        ProductionTask $task,
        ProductionOrder $order,
        int $quantity,
        int $qualified,
        int $defective,
    ): void {
        $changes = [];

        foreach ([$task, $order] as $model) {
            $next = [
                'produced_quantity' => $this->milli($model->produced_quantity) + $quantity,
                'qualified_quantity' => $this->milli($model->qualified_quantity) + $qualified,
                'defective_quantity' => $this->milli($model->defective_quantity) + $defective,
            ];

            if ($next['produced_quantity'] < 0
                || $next['qualified_quantity'] < 0
                || $next['defective_quantity'] < 0) {
                throw new ApiException('冲销数量超过任务或订单累计数量，数据不一致', 422);
            }

            $changes[] = [$model, $next];
        }

        foreach ($changes as [$model, $next]) {
            // Progress counters are derived, never mass-assignable from input.
            $model->forceFill([
                'produced_quantity' => $this->decimal($next['produced_quantity']),
                'qualified_quantity' => $this->decimal($next['qualified_quantity']),
                'defective_quantity' => $this->decimal($next['defective_quantity']),
            ])->save();
        }
    }
}
