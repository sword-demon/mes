<?php

namespace App\Modules\Production\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Organization\Model\Employee;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use Illuminate\Support\Facades\DB;

class ProcessRecordService
{
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

            $sn = $data['sn'] ?? $locked->sn;

            if (is_string($sn) && $sn !== '') {
                $this->ensureSerialNumber($sn, $task);
            }

            $locked->update([
                'status' => ProcessRecord::PROCESSING,
                'employee_id' => $employee->id,
                'team_id' => $employee->team_id,
                'equipment_id' => $data['equipment_id'] ?? $locked->equipment_id,
                'sn' => $sn,
                'started_at' => $locked->started_at ?? now(),
            ]);

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
            $this->ensureStatus($locked, ProcessRecord::PROCESSING, '完成加工');
            $requiresInspection = $locked->requiresInspection();

            $locked->update([
                'status' => $requiresInspection ? ProcessRecord::INSPECTING : ProcessRecord::PENDING_TRANSFER,
                'completed_at' => now(),
                'submitted_at' => $requiresInspection ? $locked->submitted_at : now(),
            ]);

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
            $this->ensureStatus($locked, ProcessRecord::INSPECTING, '检验');

            if ($passed) {
                $locked->update([
                    'status' => ProcessRecord::INSPECTED,
                    'inspected_at' => now(),
                ]);

                return $locked;
            }

            if ($reason === null || trim($reason) === '') {
                throw new ApiException('检验不合格必须填写原因', 422);
            }

            $this->markForRework($locked, $reason);

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

            if ($locked->requiresInspection() && $locked->status !== ProcessRecord::INSPECTED) {
                throw new ApiException('需检验工序尚未检验通过，不能提交下道', 422);
            }

            $this->ensureStatus($locked, ProcessRecord::INSPECTED, '提交下道');

            $locked->update([
                'status' => ProcessRecord::PENDING_TRANSFER,
                'submitted_at' => now(),
            ]);

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
            $this->ensureStatus($locked, ProcessRecord::PENDING_TRANSFER, '接收流转');

            $locked->update([
                'status' => ProcessRecord::TRANSFERRED,
                'received_at' => now(),
            ]);

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
