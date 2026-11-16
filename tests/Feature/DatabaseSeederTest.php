<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Database\Seeders\DemoDataConsistencyChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        // These two bounds guard the decimal scale, not just the row counts.
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

it('passes the consistency checker for the full demo seed', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    expect(fn () => (new DemoDataConsistencyChecker)->assertConsistent())
        ->not->toThrow(RuntimeException::class);
});

it('rejects each inconsistent demo data rule', function (Closure $breakData) {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();
    $breakData();

    expect(fn () => (new DemoDataConsistencyChecker)->assertConsistent())
        ->toThrow(RuntimeException::class, '一致性自检失败');
})->with([
    'report quantity imbalance' => [function (): void {
        $report = ProductionReport::query()->where('type', '<>', ProductionReport::REVERSED)->firstOrFail();
        $report->update(['qualified_quantity' => (float) $report->quantity + 1]);
    }],
    'order produced quantity drift' => [function (): void {
        $order = ProductionOrder::query()->firstOrFail();
        $order->forceFill(['produced_quantity' => (float) $order->produced_quantity + 1])->save();
    }],
    'task report exceeds plan' => [function (): void {
        $task = ProductionTask::query()->where('produced_quantity', '>', 0)->firstOrFail();
        $task->update(['planned_quantity' => 0.001]);
    }],
    'inventory ledger drift' => [function (): void {
        $inventory = Inventory::query()->firstOrFail();
        $inventory->update(['current_quantity' => (float) $inventory->current_quantity + 1]);
    }],
    'serial number batch mismatch' => [function (): void {
        $sn = ProductSn::query()->firstOrFail();
        $otherOrder = ProductionOrder::query()
            ->where('id', '<>', $sn->production_order_id)
            ->firstOrFail();

        $sn->update(['production_order_id' => $otherOrder->id]);
    }],
    'repair or offline output growth' => [function (): void {
        $samples = EquipmentData::query()
            ->where('status', Equipment::OFFLINE)
            ->where('message_type', EquipmentData::TYPE_DATA)
            ->orderBy('recorded_at')
            ->limit(2)
            ->get();

        $samples[1]->update(['output' => (int) $samples[0]->output + 10]);
    }],
]);

it('is idempotent across two consecutive runs', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    $counts = seededTableCounts();

    $this->seed();

    expect(seededTableCounts())->toBe($counts);
});

it('distributes demo time relative to now with orders and reports today', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    $start = now(config('app.business_timezone'))->startOfDay()->utc();
    $end = $start->copy()->addDay();

    expect(ProductionOrder::query()->where('created_at', '>=', $start)->where('created_at', '<', $end)->count())->toBeGreaterThanOrEqual(4)
        ->and(ProductionReport::query()->where('reported_at', '>=', $start)->where('reported_at', '<', $end)->count())->toBeGreaterThanOrEqual(20)
        ->and(ProductionReport::query()->where('reported_at', '>=', now()->subDays(7))->count())->toBeGreaterThan(0)
        ->and(ProductionReport::query()->where('reported_at', '>', now())->count())->toBe(0)
        ->and(ProductionReport::query()->where('reported_at', '<', now()->subDays(30))->count())->toBe(0);
});

it('keeps today orders and reports when seeded just after midnight', function () {
    $this->travelTo('2026-11-16 16:05:00');

    $this->seed();

    $start = now(config('app.business_timezone'))->startOfDay()->utc();
    $end = $start->copy()->addDay();

    expect(ProductionOrder::query()->where('created_at', '>=', $start)->where('created_at', '<', $end)->count())->toBeGreaterThanOrEqual(4)
        ->and(ProductionReport::query()->where('reported_at', '>=', $start)->where('reported_at', '<', $end)->count())->toBeGreaterThanOrEqual(20)
        ->and(ProductionReport::query()->where('reported_at', '>', now())->count())->toBe(0);
});

it('rejects output growth on samples marked repair or offline', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    $equipment = Equipment::query()->where('status', Equipment::RUNNING)->firstOrFail();
    $samples = EquipmentData::query()
        ->where('equipment_id', $equipment->id)
        ->where('message_type', EquipmentData::TYPE_DATA)
        ->orderBy('recorded_at')
        ->get();

    $samples[0]->update(['status' => Equipment::REPAIR]);
    $samples[1]->update(['status' => Equipment::REPAIR, 'output' => (int) $samples[1]->output + 10]);

    expect(fn () => (new DemoDataConsistencyChecker)->assertConsistent())
        ->toThrow(RuntimeException::class, '一致性自检失败');
});

/**
 * @return array<string, int>
 */
function seededTableCounts(): array
{
    return collect(Schema::getTableListing(null, false))
        ->reject(fn (string $table): bool => in_array($table, ['migrations', 'sqlite_sequence'], true))
        ->sort()
        ->values()
        ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
        ->all();
}
