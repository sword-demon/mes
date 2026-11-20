<?php

namespace App\Modules\Production\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Service\EquipmentService;
use App\Modules\Organization\Model\Employee;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Facades\DB;

class ProcessRecordService
{
    public function __construct(
        private readonly EquipmentService $equipments,
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * A task inherits its whole route, so every step is visible as 待加工 from
     * the moment the task exists instead of appearing only after it starts.
     */
    public function createForTask(ProductionTask $task): void
    {
        $operations = RouteOperation::query()
            ->where('route_id', $task->route_id)
            ->orderBy('sequence')
            ->get();

        foreach ($operations as $operation) {
            ProcessRecord::query()->firstOrCreate(
                [
                    'production_task_id' => $task->id,
                    'route_operation_id' => $operation->id,
                ],
                [
                    'sequence' => $operation->sequence,
                    'status' => ProcessRecord::PENDING,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function start(ProcessRecord $record, array $data): ProcessRecord
    {
        return DB::transaction(function () use ($record, $data): ProcessRecord {
            $task = ProductionTask::query()->lockForUpdate()->whereKey($record->production_task_id)->firstOrFail();
            $this->dataScope->ensure($task);
            $locked = ProcessRecord::query()->lockForUpdate()->whereKey($record->getKey())->firstOrFail();

            if ($task->status === ProductionTask::COMPLETED) {
                throw new ApiException('生产任务已完成，不能开始工序', 422);
            }

            if ($locked->status === ProcessRecord::PENDING) {
                $this->ensurePreviousOperationTransferred($locked, $task);
            } elseif ($locked->status === ProcessRecord::REWORKING) {
                // An exception recorded before the step ever started must not
                // turn into a back door around the previous step.
                if ($locked->started_at === null) {
                    $this->ensurePreviousOperationTransferred($locked, $task);
                }
            } else {
                throw new ApiException(
                    "工序当前状态为「{$locked->statusLabel()}」，不能开始加工",
                    422,
                );
            }

            $before = $locked->statusLabel();
            $employeeId = $data['employee_id'] ?? $locked->employee_id ?? $task->owner_id;

            if ($employeeId === null) {
                throw new ApiException('工序执行必须指定操作人员', 422);
            }

            $employee = Employee::query()
                ->whereKey($employeeId)
                ->where('status', true)
                ->first();

            if ($employee === null) {
                throw new ApiException('操作人员不存在或已停用，不能开始工序', 422);
            }

            $equipmentId = $data['equipment_id'] ?? $locked->equipment_id;

            if ($equipmentId !== null) {
                $orderLineId = $task->production_line_id
                    ?? ProductionOrder::query()->whereKey($task->production_order_id)->value('production_line_id');

                $equipment = $this->equipments->available(
                    (int) $equipmentId,
                    $orderLineId !== null ? (int) $orderLineId : null,
                    '设备不存在或当前不可用，不能开始工序',
                );
                $equipmentId = $equipment->id;
            }

            $sn = $data['sn'] ?? $locked->sn;

            if ($locked->sn !== null && $sn !== null && $sn !== $locked->sn) {
                throw new ApiException('SN 已绑定，不能更换', 422);
            }

            if (is_string($sn) && $sn !== '') {
                $this->ensureSerialNumber($sn, $task);
                $this->ensureSerialNumberFreeForTask($sn, $task);
            }

            $locked->update([
                'status' => ProcessRecord::PROCESSING,
                'employee_id' => $employee->id,
                'team_id' => $employee->team_id,
                'equipment_id' => $equipmentId,
                'sn' => $sn,
                'started_at' => $locked->started_at ?? now(),
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.flow.start',
                actionName: '工序开工',
                objectType: '工序执行',
                objectId: $locked->id,
                objectName: $this->taskLabel($locked),
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel(), '操作人员' => $employee->name],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    public function complete(ProcessRecord $record): ProcessRecord
    {
        return DB::transaction(function () use ($record): ProcessRecord {
            $locked = ProcessRecord::query()->lockForUpdate()->whereKey($record->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();
            $this->ensureStatus($locked, ProcessRecord::PROCESSING, '完成加工');
            $this->ensureSerialNumberUsable($locked);
            $requiresInspection = $locked->requiresInspection();

            $locked->update([
                'status' => $requiresInspection ? ProcessRecord::INSPECTING : ProcessRecord::PENDING_TRANSFER,
                'completed_at' => now(),
                'submitted_at' => $requiresInspection ? $locked->submitted_at : now(),
                'inspection_cycle' => $requiresInspection
                    ? $locked->inspection_cycle + 1
                    : $locked->inspection_cycle,
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.flow.complete',
                actionName: '工序完工',
                objectType: '工序执行',
                objectId: $locked->id,
                objectName: $this->taskLabel($locked),
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel()],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    public function inspect(ProcessRecord $record, bool $passed, ?string $reason): ProcessRecord
    {
        return DB::transaction(function () use ($record, $passed, $reason): ProcessRecord {
            $locked = ProcessRecord::query()->lockForUpdate()->whereKey($record->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();
            $this->ensureStatus($locked, ProcessRecord::INSPECTING, '检验');
            $this->ensureSerialNumberUsable($locked);

            if ($passed) {
                $locked->update([
                    'status' => ProcessRecord::INSPECTED,
                    'inspected_at' => now(),
                ]);
            } else {
                if ($reason === null || trim($reason) === '') {
                    throw new ApiException('检验不合格必须填写原因', 422);
                }

                $this->markForRework($locked, $reason);
            }

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.flow.inspect',
                actionName: '工序检验判定',
                objectType: '工序执行',
                objectId: $locked->id,
                objectName: $this->taskLabel($locked),
                before: ['状态' => $before],
                after: [
                    '状态' => $locked->statusLabel(),
                    '检验结果' => $passed ? '合格' : '不合格',
                    ...($passed ? [] : ['不合格原因' => $reason]),
                ],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    public function submit(ProcessRecord $record): ProcessRecord
    {
        return DB::transaction(function () use ($record): ProcessRecord {
            $locked = ProcessRecord::query()->lockForUpdate()->whereKey($record->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();
            $this->ensureSerialNumberUsable($locked);

            if ($locked->requiresInspection() && $locked->status !== ProcessRecord::INSPECTED) {
                throw new ApiException('需检验工序尚未检验通过，不能提交下道', 422);
            }

            $this->ensureStatus($locked, ProcessRecord::INSPECTED, '提交下道');

            $locked->update([
                'status' => ProcessRecord::PENDING_TRANSFER,
                'submitted_at' => now(),
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.flow.submit',
                actionName: '提交下道',
                objectType: '工序执行',
                objectId: $locked->id,
                objectName: $this->taskLabel($locked),
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel()],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    public function receive(ProcessRecord $record): ProcessRecord
    {
        return DB::transaction(function () use ($record): ProcessRecord {
            $locked = ProcessRecord::query()->lockForUpdate()->whereKey($record->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();
            $this->ensureStatus($locked, ProcessRecord::PENDING_TRANSFER, '接收流转');
            $this->ensureSerialNumberUsable($locked);

            $locked->update([
                'status' => ProcessRecord::TRANSFERRED,
                'received_at' => now(),
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.flow.receive',
                actionName: '下道接收',
                objectType: '工序执行',
                objectId: $locked->id,
                objectName: $this->taskLabel($locked),
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel()],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    public function exception(ProcessRecord $record, string $reason): ProcessRecord
    {
        return DB::transaction(function () use ($record, $reason): ProcessRecord {
            $locked = ProcessRecord::query()->lockForUpdate()->whereKey($record->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();

            if (! in_array($locked->status, [
                ProcessRecord::PROCESSING,
                ProcessRecord::INSPECTING,
                ProcessRecord::INSPECTED,
                ProcessRecord::PENDING_TRANSFER,
                ProcessRecord::REWORKING,
            ], true)) {
                throw new ApiException(
                    "工序当前状态为「{$locked->statusLabel()}」，不能上报异常",
                    422,
                );
            }

            $locked->update([
                'status' => ProcessRecord::REWORKING,
                'exception_reason' => $reason,
                'exception_at' => now(),
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.flow.exception',
                actionName: '工序异常',
                objectType: '工序执行',
                objectId: $locked->id,
                objectName: $this->taskLabel($locked),
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel(), '异常原因' => $reason],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    public function rework(ProcessRecord $record, string $reason): ProcessRecord
    {
        return DB::transaction(function () use ($record, $reason): ProcessRecord {
            $locked = ProcessRecord::query()->lockForUpdate()->whereKey($record->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();
            $reworkCountBefore = $locked->rework_count;

            if (! in_array($locked->status, [
                ProcessRecord::PROCESSING,
                ProcessRecord::INSPECTING,
                ProcessRecord::INSPECTED,
                ProcessRecord::PENDING_TRANSFER,
            ], true)) {
                throw new ApiException(
                    "工序当前状态为「{$locked->statusLabel()}」，不能返工",
                    422,
                );
            }

            $this->markForRework($locked, $reason);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.flow.rework',
                actionName: '工序返工',
                objectType: '工序执行',
                objectId: $locked->id,
                objectName: $this->taskLabel($locked),
                before: ['状态' => $before, '返工次数' => $reworkCountBefore],
                after: ['状态' => $locked->statusLabel(), '返工次数' => $locked->rework_count, '返工原因' => $reason],
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    private function ensurePreviousOperationTransferred(ProcessRecord $record, ProductionTask $task): void
    {
        $previous = ProcessRecord::query()
            ->where('production_task_id', $record->production_task_id)
            ->where('sequence', '<', $record->sequence)
            ->orderByDesc('sequence')
            ->first();

        if ($previous === null) {
            $hasEarlierOperation = RouteOperation::query()
                ->where('route_id', $task->route_id)
                ->where('sequence', '<', $record->sequence)
                ->exists();

            if (! $hasEarlierOperation) {
                return;
            }
        }

        if ($previous === null || $previous->status !== ProcessRecord::TRANSFERRED) {
            throw new ApiException('上道工序尚未流转，不能开始本道工序', 422);
        }
    }

    private function markForRework(ProcessRecord $record, string $reason): void
    {
        $record->update([
            'status' => ProcessRecord::REWORKING,
            'rework_count' => $record->rework_count + 1,
            'rework_reason' => $reason,
            'rework_at' => now(),
        ]);
    }

    /**
     * A typo or a scrapped unit must never end up attached to an execution.
     *
     * @throws ApiException
     */
    private function ensureSerialNumber(string $sn, ProductionTask $task): void
    {
        $productSn = ProductSn::query()->where('sn', $sn)->first();

        if ($productSn === null) {
            throw new ApiException('SN 不存在', 422);
        }

        if ($productSn->isVoided()) {
            throw new ApiException('SN 已作废，不能投入生产', 422);
        }

        if ($productSn->production_order_id !== $task->production_order_id) {
            throw new ApiException('SN 不属于该生产订单', 422);
        }
    }

    /**
     * Voiding a unit after it has started must stop it at the next gate.
     * Legacy executions without a ledger entry stay allowed.
     *
     * @throws ApiException
     */
    private function ensureSerialNumberUsable(ProcessRecord $record): void
    {
        if ($record->sn === null) {
            return;
        }

        $voided = ProductSn::query()
            ->where('sn', $record->sn)
            ->where('status', ProductSn::VOIDED)
            ->exists();

        if ($voided) {
            throw new ApiException('SN 已作废，不能继续工序流转', 422);
        }
    }

    /**
     * A unit belongs to one task; the same code reappearing elsewhere means a
     * scan error or a duplicated label.
     *
     * @throws ApiException
     */
    private function ensureSerialNumberFreeForTask(string $sn, ProductionTask $task): void
    {
        $usedElsewhere = ProcessRecord::query()
            ->where('sn', $sn)
            ->where('production_task_id', '!=', $task->id)
            ->exists();

        if ($usedElsewhere) {
            throw new ApiException('SN 已用于其他生产任务', 422);
        }
    }

    /**
     * Human-readable audit label; executions have no own code, so they are
     * identified by their task and the step number within the route.
     */
    private function taskLabel(ProcessRecord $record): string
    {
        $taskCode = $record->productionTask?->code;

        return $taskCode === null ? (string) $record->id : $taskCode.' 第'.$record->sequence.'道';
    }

    /**
     * @throws ApiException
     */
    private function ensureStatus(ProcessRecord $record, string $expected, string $action): void
    {
        if ($record->status !== $expected) {
            throw new ApiException(
                "工序当前状态为「{$record->statusLabel()}」，不能{$action}",
                422,
            );
        }
    }
}
