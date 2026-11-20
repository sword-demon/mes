<?php

namespace App\Modules\Production\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductionTaskService
{
    public function __construct(
        private readonly ProcessRecordService $processRecords,
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * The order row lock serialises concurrent splits, so two requests cannot
     * both read the same remainder and together overshoot the planned quantity.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function split(ProductionOrder $order, array $data): ProductionTask
    {
        return DB::transaction(function () use ($order, $data): ProductionTask {
            $locked = ProductionOrder::query()
                ->lockForUpdate()
                ->whereKey($order->getKey())
                ->firstOrFail();
            $this->dataScope->ensure($locked);

            if (! $locked->canSplit()) {
                throw new ApiException('只有已下达的生产订单可以拆分生产任务', 422);
            }

            $requested = (string) $data['planned_quantity'];
            $scheduledMilli = (int) round((float) ProductionTask::query()
                ->where('production_order_id', $locked->id)
                ->sum('planned_quantity') * 1000);
            $requestedMilli = (int) round((float) $requested * 1000);

            if ($scheduledMilli + $requestedMilli > (int) round((float) $locked->planned_quantity * 1000)) {
                throw new ApiException('生产任务计划数量合计不能超过订单计划数量', 422);
            }

            $start = $data['planned_start_at'] ?? $locked->planned_start_at;
            $end = array_key_exists('planned_end_at', $data) ? $data['planned_end_at'] : $locked->planned_end_at;

            if ($end !== null && Carbon::parse($end)->lt(Carbon::parse($start))) {
                throw new ApiException('计划结束时间不能早于计划开始时间', 422);
            }

            $sequence = ProductionTask::query()->where('production_order_id', $locked->id)->count() + 1;

            $task = ProductionTask::query()->create([
                'code' => $locked->code.'-T'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
                'production_order_id' => $locked->id,
                'product_id' => $locked->product_id,
                'route_id' => $locked->route_id,
                'planned_quantity' => $requested,
                'planned_start_at' => $start,
                'planned_end_at' => $end,
                'status' => ProductionTask::PENDING,
            ]);

            $this->processRecords->createForTask($task);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.task.split',
                actionName: '拆分生产任务',
                objectType: '生产任务',
                objectId: $task->id,
                objectName: $task->code,
                after: ['计划数量' => $requested, '所属订单' => $locked->code],
            ));

            return $task;
        });
    }

    /**
     * A fully assigned task is in production; reassigning a running or paused
     * task keeps its current state.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function assign(ProductionTask $task, array $data): ProductionTask
    {
        return DB::transaction(function () use ($task, $data): ProductionTask {
            $locked = ProductionTask::query()
                ->lockForUpdate()
                ->whereKey($task->getKey())
                ->firstOrFail();
            $this->dataScope->ensure($locked);

            if ($locked->status === ProductionTask::COMPLETED) {
                throw new ApiException('生产任务已完成，不能重新分配', 422);
            }

            $this->dataScope->ensureLine((int) $data['production_line_id']);
            $this->ensureActiveLine((int) $data['production_line_id']);
            $this->ensureActiveTeam((int) $data['team_id']);
            $this->ensureActiveEmployee((int) $data['owner_id'], (int) $data['team_id']);

            $locked->load(['team', 'owner', 'productionLine']);
            $before = $this->snapshot($locked);

            $locked->update([
                ...$data,
                'status' => $locked->status === ProductionTask::PENDING
                    ? ProductionTask::IN_PROGRESS
                    : $locked->status,
            ]);
            $locked->refresh()->load(['team', 'owner', 'productionLine']);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.task.assign',
                actionName: '生产派工',
                objectType: '生产任务',
                objectId: $locked->id,
                objectName: $locked->code,
                before: $before,
                after: $this->snapshot($locked),
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    public function transition(ProductionTask $task, string $target): ProductionTask
    {
        return DB::transaction(function () use ($task, $target): ProductionTask {
            $locked = ProductionTask::query()
                ->lockForUpdate()
                ->whereKey($task->getKey())
                ->firstOrFail();
            $this->dataScope->ensure($locked);

            [$action, $actionName] = match ($target) {
                ProductionTask::PAUSED => ['production.task.pause', '任务暂停'],
                ProductionTask::IN_PROGRESS => ['production.task.resume', '任务恢复'],
                ProductionTask::COMPLETED => ['production.task.complete', '任务完成'],
                default => ['production.task.transition', '任务状态流转'],
            };
            $before = $locked->statusLabel();
            $locked->transitionTo($target);

            $this->operationLogs->record(new OperationLogEntry(
                action: $action,
                actionName: $actionName,
                objectType: '生产任务',
                objectId: $locked->id,
                objectName: $locked->code,
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel()],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    private function ensureActiveLine(int $lineId): void
    {
        if (! ProductionLine::query()->whereKey($lineId)->where('status', true)->exists()) {
            throw new ApiException('产线不存在或已停用，不能分配给生产任务', 422);
        }
    }

    /**
     * @throws ApiException
     */
    private function ensureActiveTeam(int $teamId): void
    {
        if (! Team::query()->whereKey($teamId)->where('status', true)->exists()) {
            throw new ApiException('班组不存在或已停用，不能分配给生产任务', 422);
        }
    }

    /**
     * @throws ApiException
     */
    private function ensureActiveEmployee(int $employeeId, int $teamId): void
    {
        $employee = Employee::query()->whereKey($employeeId)->where('status', true)->first();

        if ($employee === null) {
            throw new ApiException('负责人不存在或已停用，不能分配给生产任务', 422);
        }

        if ($employee->team_id !== $teamId) {
            throw new ApiException('负责人不属于所选班组', 422);
        }
    }

    /**
     * Human-readable labels for the audit diff; only fields an assignment or
     * transition may change are listed.
     *
     * @return array<string, mixed>
     */
    private function snapshot(ProductionTask $task): array
    {
        return [
            '产线' => $task->productionLine?->name,
            '班组' => $task->team?->name,
            '负责人' => $task->owner?->name,
            '计划数量' => (string) $task->planned_quantity,
            '状态' => $task->statusLabel(),
        ];
    }
}
