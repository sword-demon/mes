<?php

namespace App\Modules\Production\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProductionTaskService
{
    public function __construct(private readonly ProcessRecordService $processRecords) {}

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

            if ($locked->status === ProductionTask::COMPLETED) {
                throw new ApiException('生产任务已完成，不能重新分配', 422);
            }

            $this->ensureActiveLine((int) $data['production_line_id']);
            $this->ensureActiveTeam((int) $data['team_id']);
            $this->ensureActiveEmployee((int) $data['owner_id'], (int) $data['team_id']);

            $locked->update([
                ...$data,
                'status' => $locked->status === ProductionTask::PENDING
                    ? ProductionTask::IN_PROGRESS
                    : $locked->status,
            ]);

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
            $locked->transitionTo($target);

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
}
