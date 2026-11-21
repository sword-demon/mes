<?php

namespace App\Modules\Production\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Facades\DB;

class ProductionDispatchService
{
    public function __construct(
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * The task row lock serialises dispatches for the same task, so two
     * requests cannot both pass the duplicate check and race the unique index.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function create(array $data): ProductionDispatch
    {
        return DB::transaction(function () use ($data): ProductionDispatch {
            $task = ProductionTask::query()
                ->lockForUpdate()
                ->whereKey($data['production_task_id'])
                ->firstOrFail();
            $this->dataScope->ensure($task);

            if ($task->status === ProductionTask::COMPLETED) {
                throw new ApiException('生产任务已完成，不能派工', 422);
            }

            $operation = RouteOperation::query()
                ->whereKey($data['route_operation_id'])
                ->firstOrFail();

            if ($operation->route_id !== $task->route_id) {
                throw new ApiException('工序不属于该生产任务的工艺路线', 422);
            }

            $this->ensureActiveTeam((int) $data['team_id']);
            $employee = $this->ensureActiveEmployee((int) $data['employee_id'], (int) $data['team_id']);

            $duplicated = ProductionDispatch::query()
                ->where('production_task_id', $task->id)
                ->where('route_operation_id', $operation->id)
                ->where('employee_id', $data['employee_id'])
                ->exists();

            if ($duplicated) {
                throw new ApiException('该任务的工序已派给该人员，不能重复派工', 422);
            }

            $sequence = ProductionDispatch::query()
                ->where('production_task_id', $task->id)
                ->count() + 1;

            $dispatch = ProductionDispatch::query()->create([
                'code' => ProductionDispatch::codeFor($task->code, $sequence),
                'production_task_id' => $task->id,
                'route_operation_id' => $operation->id,
                'employee_id' => $data['employee_id'],
                'team_id' => $data['team_id'],
                'dispatched_at' => $data['dispatched_at'] ?? now(),
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.dispatch.create',
                actionName: '新增派工单',
                objectType: '派工单',
                objectId: $dispatch->id,
                objectName: $dispatch->code,
                after: ['所属任务' => $task->code, '作业人员' => $employee->employee_no],
            ));

            return $dispatch;
        });
    }

    /**
     * @throws ApiException
     */
    private function ensureActiveTeam(int $teamId): void
    {
        if (! Team::query()->whereKey($teamId)->where('status', true)->exists()) {
            throw new ApiException('班组不存在或已停用，不能用于派工', 422);
        }
    }

    /**
     * @throws ApiException
     */
    private function ensureActiveEmployee(int $employeeId, int $teamId): Employee
    {
        $employee = Employee::query()->whereKey($employeeId)->where('status', true)->first();

        if ($employee === null) {
            throw new ApiException('作业人员不存在或已停用，不能用于派工', 422);
        }

        if ($employee->team_id !== $teamId) {
            throw new ApiException('作业人员不属于所选班组', 422);
        }

        return $employee;
    }
}
