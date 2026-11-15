<?php

namespace Database\Seeders;

use App\Modules\Bom\Model\Bom;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Service\ProcessRecordService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class ProductionSeeder extends Seeder
{
    /**
     * Demo orders, tasks and dispatches; every code is deterministic so a
     * re-run updates in place instead of duplicating records.
     */
    private const ORDERS = [
        [
            'code' => 'PO-DEMO-001',
            'planned_quantity' => 100,
            'status' => ProductionOrder::IN_PROGRESS,
            'start_days' => -1,
            'end_days' => 5,
            'tasks' => [
                [
                    'code' => 'PO-DEMO-001-T01',
                    'planned_quantity' => 60,
                    'status' => ProductionTask::IN_PROGRESS,
                    'owner' => 'EMP004',
                    'team' => 'TEAM01',
                    'line' => 'PL01',
                    'operations' => [1, 2],
                    'workers' => ['EMP004', 'EMP002'],
                ],
                [
                    'code' => 'PO-DEMO-001-T02',
                    'planned_quantity' => 40,
                    'status' => ProductionTask::PENDING,
                    'owner' => null,
                    'team' => 'TEAM01',
                    'line' => 'PL01',
                    'operations' => [1],
                    'workers' => ['EMP002'],
                ],
            ],
        ],
        [
            'code' => 'PO-DEMO-002',
            'planned_quantity' => 60,
            'status' => ProductionOrder::IN_PROGRESS,
            'start_days' => -2,
            'end_days' => 3,
            'tasks' => [
                [
                    'code' => 'PO-DEMO-002-T01',
                    'planned_quantity' => 60,
                    'status' => ProductionTask::IN_PROGRESS,
                    'owner' => 'EMP002',
                    'team' => 'TEAM01',
                    'line' => 'PL01',
                    'operations' => [3],
                    'workers' => ['EMP002'],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $bom = Bom::query()->where('code', 'BOM-P001-01')->first();
        $route = ProcessRoute::query()->where('code', 'RT-P001-01')->first();
        $line = ProductionLine::query()->where('code', 'PL01')->first();
        $team = Team::query()->where('code', 'TEAM01')->first();

        if ($bom === null || $route === null || $line === null || $team === null) {
            throw new RuntimeException('请先执行基础数据、产品与工艺路线 Seeder 再执行 ProductionSeeder');
        }

        $workers = Employee::query()
            ->whereIn('employee_no', ['EMP002', 'EMP004'])
            ->get()
            ->keyBy('employee_no');
        $operations = RouteOperation::query()
            ->where('route_id', $route->id)
            ->get()
            ->keyBy('sequence');

        foreach (self::ORDERS as $orderDefinition) {
            $order = ProductionOrder::query()->updateOrCreate(
                ['code' => $orderDefinition['code']],
                [
                    'product_id' => $route->product_id,
                    'product_version_id' => $bom->product_version_id,
                    'bom_id' => $bom->id,
                    'route_id' => $route->id,
                    'production_line_id' => $line->id,
                    'bom_version' => $bom->version,
                    'route_version' => $route->version,
                    'planned_quantity' => $orderDefinition['planned_quantity'],
                    'planned_start_at' => Carbon::now()->addDays($orderDefinition['start_days']),
                    'planned_end_at' => Carbon::now()->addDays($orderDefinition['end_days']),
                    'status' => $orderDefinition['status'],
                ],
            );

            ProductionBatch::query()->updateOrCreate(
                ['code' => $order->code.'-L01'],
                [
                    'production_order_id' => $order->id,
                    'quantity' => $orderDefinition['planned_quantity'],
                    'remark' => '演示批次',
                ],
            );

            foreach ($orderDefinition['tasks'] as $taskDefinition) {
                $teamId = $taskDefinition['team'] === null ? null : $team->id;
                $ownerId = $taskDefinition['owner'] === null
                    ? null
                    : $workers->get($taskDefinition['owner'])?->id;

                $task = ProductionTask::query()->updateOrCreate(
                    ['code' => $taskDefinition['code']],
                    [
                        'production_order_id' => $order->id,
                        'product_id' => $route->product_id,
                        'route_id' => $route->id,
                        'production_line_id' => $taskDefinition['line'] === null ? null : $line->id,
                        'team_id' => $teamId,
                        'owner_id' => $ownerId,
                        'planned_quantity' => $taskDefinition['planned_quantity'],
                        'planned_start_at' => $order->planned_start_at,
                        'planned_end_at' => $order->planned_end_at,
                        'status' => $taskDefinition['status'],
                    ],
                );

                foreach ($taskDefinition['operations'] as $index => $sequence) {
                    $operation = $operations->get($sequence);
                    $employee = $workers->get($taskDefinition['workers'][$index] ?? null);

                    if ($operation === null || $employee === null) {
                        throw new RuntimeException("请先执行 ProcessSeeder 与 OrganizationSeeder 以提供工序 {$sequence} 和作业人员");
                    }

                    ProductionDispatch::query()->updateOrCreate(
                        [
                            'production_task_id' => $task->id,
                            'route_operation_id' => $operation->id,
                            'employee_id' => $employee->id,
                        ],
                        [
                            'code' => ProductionDispatch::codeFor($task->code, $index + 1),
                            'team_id' => $team->id,
                            'dispatched_at' => Carbon::now()->subHours(6 - $index),
                        ],
                    );
                }

                app(ProcessRecordService::class)->createForTask($task);
            }
        }
    }
}
