<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Database\Seeders\DemoDataConsistencyChecker;

it('seeds the full demo volume required by the PRD', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    expect(ProductionOrder::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(ProductionTask::query()->count())->toBeGreaterThanOrEqual(30)
        ->and(ProductionReport::query()->count())->toBeGreaterThanOrEqual(100)
        ->and(InspectionTask::query()->count())->toBeGreaterThanOrEqual(50)
        ->and(Inventory::query()->where('batch_no', '<>', '')->count())->toBeGreaterThanOrEqual(30)
        ->and(ProductSn::query()->count())->toBeGreaterThanOrEqual(50)
        ->and(EquipmentAlarm::query()->count())->toBeGreaterThanOrEqual(20)
        ->and(Role::query()->count())->toBeGreaterThanOrEqual(8)
        ->and(User::query()->count())->toBeGreaterThanOrEqual(20)
        ->and((float) ProductionTask::query()->min('planned_quantity'))->toBeGreaterThanOrEqual(10.0)
        ->and((float) ProductionOrder::query()->min('planned_quantity'))->toBeGreaterThanOrEqual(10.0)
        ->and((float) ProductionReport::query()->max('quantity'))->toBeLessThanOrEqual(2.0);
});

it('covers every business status the PRD requires', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    expect(ProductionOrder::query()->distinct()->pluck('status')->sort()->values()->all())
        ->toBe(collect(array_keys(ProductionOrder::STATUS_LABELS))->sort()->values()->all())
        ->and(ProductionTask::query()->distinct()->pluck('status')->sort()->values()->all())
        ->toBe(collect(array_keys(ProductionTask::STATUS_LABELS))->sort()->values()->all())
        ->and(ProductionReport::query()->distinct()->pluck('type')->sort()->values()->all())
        ->toBe(collect(array_keys(ProductionReport::TYPE_LABELS))->sort()->values()->all())
        ->and(ProcessRecord::query()->distinct()->pluck('status')->sort()->values()->all())
        ->toBe(collect(array_keys(ProcessRecord::STATUS_LABELS))->sort()->values()->all())
        ->and(DefectiveProduct::query()->distinct()->pluck('status')->sort()->values()->all())
        ->toBe(collect(array_keys(DefectiveProduct::STATUS_LABELS))->sort()->values()->all())
        ->and(Equipment::query()->distinct()->pluck('status')->sort()->values()->all())
        ->toBe(collect(Equipment::STATUSES)->sort()->values()->all());

    expect(InspectionTask::query()->where('result', InspectionTask::PASSED)->exists())->toBeTrue()
        ->and(InspectionTask::query()->where('result', InspectionTask::FAILED)->exists())->toBeTrue();
});

it('keeps every seeded record internally consistent', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    $imbalancedReports = ProductionReport::query()->get()
        ->filter(fn (ProductionReport $report): bool => round((float) $report->qualified_quantity + (float) $report->defective_quantity, 3)
            !== round((float) $report->quantity, 3))
        ->pluck('code')
        ->all();

    $orderMismatches = ProductionOrder::query()->get()
        ->filter(function (ProductionOrder $order): bool {
            $reported = (float) ProductionReport::query()
                ->whereHas('productionTask', fn ($query) => $query->where('production_order_id', $order->id))
                ->where('type', '<>', ProductionReport::REVERSED)
                ->sum('quantity');

            return round($reported, 3) !== round((float) $order->produced_quantity, 3);
        })
        ->pluck('code')
        ->all();

    $reportedByTask = ProductionReport::query()
        ->where('type', '<>', ProductionReport::REVERSED)
        ->selectRaw('production_task_id, SUM(quantity) as reported_quantity')
        ->groupBy('production_task_id')
        ->pluck('reported_quantity', 'production_task_id');

    $overplannedTasks = ProductionTask::query()->get()
        ->filter(fn (ProductionTask $task): bool => round((float) ($reportedByTask[$task->id] ?? 0), 3)
            > round((float) $task->planned_quantity, 3))
        ->pluck('code')
        ->all();

    $ledgerMismatches = Inventory::query()->get()
        ->filter(function (Inventory $inventory): bool {
            $net = (float) InventoryTransaction::query()
                ->where('inventory_id', $inventory->id)
                ->sum('quantity');

            return round($net, 3) !== round((float) $inventory->current_quantity, 3);
        })
        ->pluck('id')
        ->all();

    $sns = ProductSn::query()->with('productionBatch')->get();
    $orphanSns = $sns->filter(fn (ProductSn $sn): bool => $sn->productionBatch === null)->pluck('sn')->all();
    $crossBatchSns = $sns
        ->filter(fn (ProductSn $sn): bool => $sn->productionBatch !== null
            && $sn->productionBatch->production_order_id !== $sn->production_order_id)
        ->pluck('sn')
        ->all();

    $repairOrOffline = Equipment::query()
        ->whereIn('status', [Equipment::REPAIR, Equipment::OFFLINE])
        ->pluck('id');

    $growingOutput = EquipmentData::query()
        ->whereIn('equipment_id', $repairOrOffline)
        ->where('message_type', EquipmentData::TYPE_DATA)
        ->orderBy('recorded_at')
        ->get()
        ->groupBy('equipment_id')
        ->filter(fn ($samples): bool => $samples->pluck('output')->unique()->count() > 1)
        ->keys()
        ->all();

    expect($imbalancedReports)->toBe([])
        ->and($orderMismatches)->toBe([])
        ->and($overplannedTasks)->toBe([])
        ->and($ledgerMismatches)->toBe([])
        ->and(ProductSn::query()->distinct()->count('sn'))->toBe($sns->count())
        ->and($orphanSns)->toBe([])
        ->and($crossBatchSns)->toBe([])
        ->and(EquipmentData::query()->whereIn('equipment_id', $repairOrOffline)->distinct()->count('equipment_id'))
        ->toBe($repairOrOffline->count())
        ->and($growingOutput)->toBe([]);

    expect(fn () => (new DemoDataConsistencyChecker)->assertConsistent())
        ->not->toThrow(RuntimeException::class);
});

it('is idempotent across two consecutive runs', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    $counts = [
        'orders' => ProductionOrder::query()->count(),
        'tasks' => ProductionTask::query()->count(),
        'reports' => ProductionReport::query()->count(),
        'batches' => ProductionBatch::query()->count(),
        'dispatches' => ProductionDispatch::query()->count(),
        'sns' => ProductSn::query()->count(),
        'inspections' => InspectionTask::query()->count(),
        'inventories' => Inventory::query()->count(),
        'transactions' => InventoryTransaction::query()->count(),
        'alarms' => EquipmentAlarm::query()->count(),
        'equipment_data' => EquipmentData::query()->count(),
        'process_records' => ProcessRecord::query()->count(),
        'defects' => DefectiveProduct::query()->count(),
        'roles' => Role::query()->count(),
        'users' => User::query()->count(),
    ];

    $this->seed();

    expect([
        'orders' => ProductionOrder::query()->count(),
        'tasks' => ProductionTask::query()->count(),
        'reports' => ProductionReport::query()->count(),
        'batches' => ProductionBatch::query()->count(),
        'dispatches' => ProductionDispatch::query()->count(),
        'sns' => ProductSn::query()->count(),
        'inspections' => InspectionTask::query()->count(),
        'inventories' => Inventory::query()->count(),
        'transactions' => InventoryTransaction::query()->count(),
        'alarms' => EquipmentAlarm::query()->count(),
        'equipment_data' => EquipmentData::query()->count(),
        'process_records' => ProcessRecord::query()->count(),
        'defects' => DefectiveProduct::query()->count(),
        'roles' => Role::query()->count(),
        'users' => User::query()->count(),
    ])->toBe($counts);
});

it('distributes demo time relative to now with orders and reports today', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    expect(ProductionOrder::query()->whereDate('created_at', now()->toDateString())->count())->toBeGreaterThanOrEqual(4)
        ->and(ProductionReport::query()->whereDate('reported_at', now()->toDateString())->count())->toBeGreaterThanOrEqual(20)
        ->and(ProductionReport::query()->where('reported_at', '>=', now()->subDays(7))->count())->toBeGreaterThan(0)
        ->and(ProductionReport::query()->where('reported_at', '>', now())->count())->toBe(0)
        ->and(ProductionReport::query()->where('reported_at', '<', now()->subDays(30))->count())->toBe(0);
});

it('aborts when seeded data violates the consistency rules', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    $report = ProductionReport::query()->where('type', '<>', ProductionReport::REVERSED)->firstOrFail();
    $report->update(['qualified_quantity' => (float) $report->quantity + 1]);

    expect(fn () => (new DemoDataConsistencyChecker)->assertConsistent())
        ->toThrow(RuntimeException::class, '一致性自检失败');
});
