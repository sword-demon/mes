<?php

namespace Database\Seeders;

use App\Modules\Bom\Model\Bom;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Material\Service\Quantity;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Production\Service\ProcessRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionSeeder extends Seeder
{
    public function __construct(private readonly ProcessRecordService $processRecords) {}

    /**
     * Twenty orders cover all nine statuses; the repeated statuses give the
     * dashboard enough live work to show.
     */
    private const ORDERS = [
        ProductionOrder::DRAFT,
        ProductionOrder::PENDING,
        ProductionOrder::APPROVED,
        ProductionOrder::RELEASED,
        ProductionOrder::IN_PROGRESS,
        ProductionOrder::PAUSED,
        ProductionOrder::COMPLETED,
        ProductionOrder::CLOSED,
        ProductionOrder::CANCELLED,
        ProductionOrder::IN_PROGRESS,
        ProductionOrder::IN_PROGRESS,
        ProductionOrder::IN_PROGRESS,
        ProductionOrder::IN_PROGRESS,
        ProductionOrder::PAUSED,
        ProductionOrder::COMPLETED,
        ProductionOrder::COMPLETED,
        ProductionOrder::COMPLETED,
        ProductionOrder::CLOSED,
        ProductionOrder::RELEASED,
        ProductionOrder::RELEASED,
    ];

    private const ORDERS_WITH_REPORTS = [
        ProductionOrder::IN_PROGRESS,
        ProductionOrder::PAUSED,
        ProductionOrder::COMPLETED,
        ProductionOrder::CLOSED,
    ];

    private const TASK_PLANNED_QUANTITY = 10;

    private const REPORTS_PER_TASK = 5;

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
            ->whereIn('employee_no', ['EMP002', 'EMP004', 'EMP005', 'EMP006', 'EMP009'])
            ->get()
            ->keyBy('employee_no');
        $operations = RouteOperation::query()
            ->where('route_id', $route->id)
            ->get()
            ->keyBy('sequence');
        $materialBatchNo = Inventory::query()
            ->where('batch_no', '<>', '')
            ->orderBy('batch_no')
            ->value('batch_no');

        DB::transaction(function () use ($bom, $route, $line, $team, $workers, $operations, $materialBatchNo): void {
            foreach (self::ORDERS as $index => $status) {
                $order = $this->seedOrder($index, $status, $bom, $route, $line);
                $taskCount = 1 + $index % 2;

                for ($taskNumber = 1; $taskNumber <= $taskCount; $taskNumber++) {
                    $task = $this->seedTask($order, $status, $taskNumber, $route, $line, $team, $workers);
                    $this->seedDispatches($task, $operations, $team, $workers);
                    $this->processRecords->createForTask($task);
                }
            }

            $this->seedProcessStatusCoverage($team, $workers);
            $this->seedReports($route, $workers);
            $this->recalculateTotals();
            $this->seedSerialNumbers($workers, $materialBatchNo);
        });
    }

    private function seedOrder(
        int $index,
        string $status,
        Bom $bom,
        ProcessRoute $route,
        ProductionLine $line,
    ): ProductionOrder {
        $code = 'PO-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
        $closed = in_array($status, [ProductionOrder::COMPLETED, ProductionOrder::CLOSED], true);
        $plannedStart = $closed
            ? now()->subDays(10 + $index % 5)
            : now()->addDays($index % 7 - 2);

        $order = ProductionOrder::query()->updateOrCreate(
            ['code' => $code],
            [
                'product_id' => $route->product_id,
                'product_version_id' => $bom->product_version_id,
                'bom_id' => $bom->id,
                'route_id' => $route->id,
                'production_line_id' => $line->id,
                'bom_version' => $bom->version,
                'route_version' => $route->version,
                'planned_quantity' => (1 + $index % 2) * self::TASK_PLANNED_QUANTITY,
                'planned_start_at' => $plannedStart,
                'planned_end_at' => $plannedStart->copy()->addDays(5),
                'status' => $status,
                'remark' => '演示订单',
            ],
        );

        $createdAt = $index >= 16
            ? $this->earlierToday(($index - 16 + 1) * 10)
            : now()->subDays($index + 1)->subHours($index % 8);

        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        ProductionBatch::query()->updateOrCreate(
            ['code' => $order->code.'-L01'],
            [
                'production_order_id' => $order->id,
                'quantity' => (int) $order->planned_quantity,
                'remark' => '演示批次',
            ],
        );

        return $order;
    }

    /**
     * @param  Collection<string, Employee>  $workers
     */
    private function seedTask(
        ProductionOrder $order,
        string $orderStatus,
        int $taskNumber,
        ProcessRoute $route,
        ProductionLine $line,
        Team $team,
        Collection $workers,
    ): ProductionTask {
        $status = match (true) {
            in_array($orderStatus, [ProductionOrder::COMPLETED, ProductionOrder::CLOSED], true) => ProductionTask::COMPLETED,
            $orderStatus === ProductionOrder::IN_PROGRESS => ProductionTask::IN_PROGRESS,
            $orderStatus === ProductionOrder::PAUSED => ProductionTask::PAUSED,
            default => ProductionTask::PENDING,
        };
        $owner = $status === ProductionTask::PENDING
            ? null
            : $workers->get($taskNumber % 2 === 1 ? 'EMP002' : 'EMP004');

        return ProductionTask::query()->updateOrCreate(
            ['code' => $order->code.'-T'.str_pad((string) $taskNumber, 2, '0', STR_PAD_LEFT)],
            [
                'production_order_id' => $order->id,
                'product_id' => $route->product_id,
                'route_id' => $route->id,
                'production_line_id' => $line->id,
                'team_id' => $team->id,
                'owner_id' => $owner?->id,
                'planned_quantity' => self::TASK_PLANNED_QUANTITY,
                'planned_start_at' => $order->planned_start_at,
                'planned_end_at' => $order->planned_end_at,
                'status' => $status,
            ],
        );
    }

    /**
     * @param  Collection<int, RouteOperation>  $operations
     * @param  Collection<string, Employee>  $workers
     */
    private function seedDispatches(ProductionTask $task, Collection $operations, Team $team, Collection $workers): void
    {
        foreach ([1, 2] as $sequence) {
            $operation = $operations->get($sequence);
            $employee = $workers->get($sequence === 1 ? 'EMP002' : 'EMP004');

            if ($operation === null || $employee === null) {
                continue;
            }

            ProductionDispatch::query()->updateOrCreate(
                ['code' => ProductionDispatch::codeFor($task->code, $sequence)],
                [
                    'production_task_id' => $task->id,
                    'route_operation_id' => $operation->id,
                    'employee_id' => $employee->id,
                    'team_id' => $team->id,
                    'dispatched_at' => now()->subHours(12 - $sequence),
                ],
            );
        }
    }

    /**
     * @param  Collection<string, Employee>  $workers
     */
    private function seedProcessStatusCoverage(Team $team, Collection $workers): void
    {
        $statuses = [
            ProcessRecord::PENDING,
            ProcessRecord::PROCESSING,
            ProcessRecord::INSPECTING,
            ProcessRecord::INSPECTED,
            ProcessRecord::PENDING_TRANSFER,
            ProcessRecord::TRANSFERRED,
            ProcessRecord::REWORKING,
        ];
        $tasks = ProductionTask::query()
            ->whereHas(
                'productionOrder',
                fn ($query) => $query->whereIn('status', self::ORDERS_WITH_REPORTS),
            )
            ->orderBy('id')
            ->limit(count($statuses))
            ->get();
        $employee = $workers->get('EMP002');
        $base = now()->subHours(6);

        foreach ($statuses as $index => $status) {
            $task = $tasks->get($index);
            $record = $task === null
                ? null
                : ProcessRecord::query()
                    ->where('production_task_id', $task->id)
                    ->orderBy('sequence')
                    ->first();

            if ($task === null || $record === null) {
                continue;
            }

            $record->update([
                'status' => $status,
                'employee_id' => $task->owner_id ?? $employee?->id,
                'team_id' => $team->id,
                'started_at' => $status === ProcessRecord::PENDING ? null : $base->copy(),
                'completed_at' => in_array($status, [
                    ProcessRecord::INSPECTING,
                    ProcessRecord::INSPECTED,
                    ProcessRecord::PENDING_TRANSFER,
                    ProcessRecord::TRANSFERRED,
                ], true) ? $base->copy()->addHour() : null,
                'inspected_at' => $status === ProcessRecord::INSPECTED ? $base->copy()->addHours(2) : null,
                'submitted_at' => in_array($status, [
                    ProcessRecord::INSPECTING,
                    ProcessRecord::PENDING_TRANSFER,
                    ProcessRecord::TRANSFERRED,
                ], true) ? $base->copy()->addHours(2) : null,
                'received_at' => $status === ProcessRecord::TRANSFERRED ? $base->copy()->addHours(3) : null,
                'exception_reason' => $status === ProcessRecord::REWORKING ? '演示工序异常' : null,
                'exception_at' => $status === ProcessRecord::REWORKING ? $base->copy()->addMinutes(30) : null,
                'rework_reason' => $status === ProcessRecord::REWORKING ? '演示返工' : null,
                'rework_at' => $status === ProcessRecord::REWORKING ? $base->copy()->addHour() : null,
                'rework_count' => $status === ProcessRecord::REWORKING ? 1 : 0,
                'inspection_cycle' => in_array($status, [ProcessRecord::INSPECTING, ProcessRecord::INSPECTED], true) ? 1 : 0,
            ]);
        }
    }

    /**
     * @param  Collection<string, Employee>  $workers
     */
    private function seedReports(ProcessRoute $route, Collection $workers): void
    {
        $operation = RouteOperation::query()
            ->where('route_id', $route->id)
            ->orderBy('sequence')
            ->first();

        if ($operation === null) {
            throw new RuntimeException('请先执行 ProcessSeeder 以提供报工工序');
        }

        $tasks = ProductionTask::query()
            ->whereHas(
                'productionOrder',
                fn ($query) => $query->whereIn('status', self::ORDERS_WITH_REPORTS),
            )
            ->orderBy('id')
            ->get();
        $employeeKeys = ['EMP002', 'EMP004', 'EMP005', 'EMP006', 'EMP009'];

        foreach ($tasks as $taskIndex => $task) {
            $completed = $task->status === ProductionTask::COMPLETED;

            for ($reportIndex = 1; $reportIndex <= self::REPORTS_PER_TASK; $reportIndex++) {
                $quantity = $completed ? 2 : 1;
                $qualified = $quantity === 2 && $reportIndex % 2 === 0 ? 1 : $quantity;
                $type = match (true) {
                    $taskIndex < 5 && $reportIndex === 2 => ProductionReport::BATCH,
                    ($taskIndex === 10 && $reportIndex === 3), ($taskIndex === 12 && $reportIndex === 4) => ProductionReport::REVERSED,
                    default => ProductionReport::NORMAL,
                };
                $reportedAt = $taskIndex < 5
                    ? $this->earlierToday(($taskIndex * 5 + $reportIndex) * 3)
                    : now()->subDays($taskIndex % 6 + 1)->subHours($reportIndex * 2)->subMinutes($taskIndex);
                $employee = $workers->get($employeeKeys[$taskIndex % count($employeeKeys)]);

                if ($employee === null) {
                    throw new RuntimeException('请先执行 OrganizationSeeder 以提供报工作业人员');
                }

                ProductionReport::query()->updateOrCreate(
                    ['code' => ProductionReport::codeFor($task->code, $reportIndex)],
                    [
                        'production_task_id' => $task->id,
                        'route_operation_id' => $operation->id,
                        'employee_id' => $employee->id,
                        'team_id' => $employee->team_id,
                        'quantity' => $quantity,
                        'qualified_quantity' => $qualified,
                        'defective_quantity' => $quantity - $qualified,
                        'type' => $type,
                        'reported_at' => $reportedAt,
                        'reversed_at' => $type === ProductionReport::REVERSED
                            ? $reportedAt->copy()->addMinute()
                            : null,
                    ],
                );
            }
        }
    }

    private function recalculateTotals(): void
    {
        $taskTotals = ProductionReport::query()
            ->where('type', '<>', ProductionReport::REVERSED)
            ->get()
            ->groupBy('production_task_id')
            ->map(fn ($reports): array => [
                'produced' => (int) $reports->sum(fn (ProductionReport $report): int => Quantity::toMilli($report->quantity)),
                'qualified' => (int) $reports->sum(fn (ProductionReport $report): int => Quantity::toMilli($report->qualified_quantity)),
                'defective' => (int) $reports->sum(fn (ProductionReport $report): int => Quantity::toMilli($report->defective_quantity)),
            ]);

        ProductionTask::query()->each(function (ProductionTask $task) use ($taskTotals): void {
            $totals = $taskTotals[$task->id] ?? ['produced' => 0, 'qualified' => 0, 'defective' => 0];

            $task->forceFill([
                'produced_quantity' => Quantity::toDecimal($totals['produced']),
                'qualified_quantity' => Quantity::toDecimal($totals['qualified']),
                'defective_quantity' => Quantity::toDecimal($totals['defective']),
            ])->save();
        });

        $orderTotals = ProductionTask::query()->get()
            ->groupBy('production_order_id')
            ->map(fn ($tasks): array => [
                'produced' => (int) $tasks->sum(fn (ProductionTask $task): int => Quantity::toMilli($task->produced_quantity)),
                'qualified' => (int) $tasks->sum(fn (ProductionTask $task): int => Quantity::toMilli($task->qualified_quantity)),
                'defective' => (int) $tasks->sum(fn (ProductionTask $task): int => Quantity::toMilli($task->defective_quantity)),
            ]);

        ProductionOrder::query()->each(function (ProductionOrder $order) use ($orderTotals): void {
            $totals = $orderTotals[$order->id] ?? ['produced' => 0, 'qualified' => 0, 'defective' => 0];

            $order->forceFill([
                'produced_quantity' => Quantity::toDecimal($totals['produced']),
                'qualified_quantity' => Quantity::toDecimal($totals['qualified']),
                'defective_quantity' => Quantity::toDecimal($totals['defective']),
            ])->save();
        });
    }

    /**
     * @param  Collection<string, Employee>  $workers
     */
    private function seedSerialNumbers(Collection $workers, ?string $materialBatchNo): void
    {
        $orders = ProductionOrder::query()->orderBy('id')->get();
        $employee = $workers->get('EMP004');
        $sequence = 0;

        foreach ($orders as $orderIndex => $order) {
            $batch = ProductionBatch::query()
                ->where('production_order_id', $order->id)
                ->orderBy('id')
                ->first();

            if ($batch === null) {
                continue;
            }

            $count = $orderIndex < 10 ? 3 : 2;

            for ($index = 0; $index < $count; $index++) {
                $sequence++;
                $voided = $sequence % 17 === 0;

                ProductSn::query()->updateOrCreate(
                    ['sn' => 'MES-DEMO-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT)],
                    [
                        'production_order_id' => $order->id,
                        'production_batch_id' => $batch->id,
                        'material_batch_no' => $materialBatchNo,
                        'employee_id' => $employee?->id,
                        'status' => $voided ? ProductSn::VOIDED : ProductSn::ACTIVE,
                        'printed_at' => now()->subDays($orderIndex % 5),
                        'voided_at' => $voided ? now()->subDay() : null,
                        'void_reason' => $voided ? '演示作废' : null,
                    ],
                );
            }
        }
    }

    /**
     * Keeps "today" demo rows on today's date even when seeding just after
     * midnight, without ever placing them in the future.
     */
    private function earlierToday(int $minutesAgo): CarbonImmutable
    {
        $now = now((string) config('app.business_timezone', 'Asia/Shanghai'));
        $startOfDay = $now->startOfDay();
        $elapsedMinutes = (int) $startOfDay->diffInMinutes($now);

        return $startOfDay->addMinutes(max(0, $elapsedMinutes - $minutesAgo))->utc();
    }
}
