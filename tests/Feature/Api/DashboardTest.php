<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionException;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\DefectiveProduct;
use Illuminate\Support\Carbon;

/**
 * @return array{0: ProductionOrder, 1: ProductionTask, 2: RouteOperation}
 */
function dashboardProductionReferences(?ProductionOrder $order = null, ?ProductionLine $line = null): array
{
    $order ??= ProductionOrder::factory()->create([
        'production_line_id' => ($line ?? ProductionLine::factory()->create())->id,
        'status' => ProductionOrder::IN_PROGRESS,
    ]);
    $task = ProductionTask::factory()->create([
        'production_order_id' => $order->id,
        'product_id' => $order->product_id,
        'route_id' => $order->route_id,
        'production_line_id' => $line?->id,
    ]);
    $operation = RouteOperation::factory()->create(['route_id' => $task->route_id]);

    return [$order, $task, $operation];
}

it('returns today business statistics without reversed reports or cancelled orders', function () {
    $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'Asia/Shanghai'));

    $line = ProductionLine::factory()->create();

    $order = ProductionOrder::factory()->create([
        'production_line_id' => $line->id,
        'planned_quantity' => 100,
        'status' => ProductionOrder::IN_PROGRESS,
        'created_at' => '2026-11-16 01:00:00',
    ]);
    ProductionOrder::factory()->create([
        'production_line_id' => $line->id,
        'planned_quantity' => 50,
        'status' => ProductionOrder::CANCELLED,
        'created_at' => '2026-11-16 02:00:00',
    ]);
    ProductionOrder::factory()->create([
        'production_line_id' => $line->id,
        'planned_quantity' => 30,
        'status' => ProductionOrder::IN_PROGRESS,
        'created_at' => '2026-11-15 15:59:59',
    ]);

    $task = ProductionTask::factory()->create([
        'production_order_id' => $order->id,
        'product_id' => $order->product_id,
        'route_id' => $order->route_id,
        'production_line_id' => $line->id,
    ]);
    $operation = RouteOperation::factory()->create(['route_id' => $task->route_id]);

    ProductionReport::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'quantity' => 10,
        'qualified_quantity' => 8,
        'defective_quantity' => 2,
        'reported_at' => '2026-11-15 16:00:00',
    ]);
    ProductionReport::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'quantity' => 4,
        'qualified_quantity' => 4,
        'defective_quantity' => 0,
        'type' => ProductionReport::REVERSED,
        'reported_at' => '2026-11-15 17:00:00',
    ]);
    ProductionReport::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'quantity' => 5,
        'qualified_quantity' => 5,
        'defective_quantity' => 0,
        'reported_at' => '2026-11-15 15:59:59',
    ]);

    Equipment::factory()->count(2)->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
        'status' => Equipment::RUNNING,
    ]);
    Equipment::factory()->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
        'status' => Equipment::RUNNING,
        'enabled' => false,
    ]);
    Equipment::factory()->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
        'status' => Equipment::FAULT,
    ]);

    $actor = userWithPermissions(['dashboard.overview']);

    bearer($actor)->getJson('/api/v1/dashboard/today')
        ->assertOk()
        ->assertJsonPath('data.date', '2026-11-16')
        ->assertJsonPath('data.order_count', 1)
        ->assertJsonPath('data.planned_quantity', '100.000')
        ->assertJsonPath('data.produced_quantity', '10.000')
        ->assertJsonPath('data.qualified_quantity', '8.000')
        ->assertJsonPath('data.defective_quantity', '2.000')
        ->assertJsonPath('data.running_equipment_count', 2);
});

it('aggregates the last seven production days on the Shanghai business calendar', function () {
    $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'Asia/Shanghai'));

    [, $task, $operation] = dashboardProductionReferences();

    foreach ([
        ['2026-11-09 16:00:00', 1, ProductionReport::NORMAL],
        ['2026-11-12 04:00:00', 2.5, ProductionReport::NORMAL],
        ['2026-11-16 01:00:00', 3, ProductionReport::NORMAL],
        ['2026-11-16 02:00:00', 99, ProductionReport::REVERSED],
        ['2026-11-09 15:59:59', 7, ProductionReport::NORMAL],
    ] as [$reportedAt, $quantity, $type]) {
        ProductionReport::factory()->create([
            'production_task_id' => $task->id,
            'route_operation_id' => $operation->id,
            'quantity' => $quantity,
            'qualified_quantity' => $quantity,
            'defective_quantity' => 0,
            'type' => $type,
            'reported_at' => $reportedAt,
        ]);
    }

    $actor = userWithPermissions(['dashboard.trend']);

    bearer($actor)->getJson('/api/v1/dashboard/production-trend')
        ->assertOk()
        ->assertJsonPath('data.days', 7)
        ->assertJsonCount(7, 'data.items')
        ->assertJsonPath('data.items.0.date', '2026-11-10')
        ->assertJsonPath('data.items.0.quantity', '1.000')
        ->assertJsonPath('data.items.1.date', '2026-11-11')
        ->assertJsonPath('data.items.1.quantity', '0.000')
        ->assertJsonPath('data.items.2.date', '2026-11-12')
        ->assertJsonPath('data.items.2.quantity', '2.500')
        ->assertJsonPath('data.items.6.date', '2026-11-16')
        ->assertJsonPath('data.items.6.quantity', '3.000');
});

it('compares valid production quantities by production line', function () {
    $lineA = ProductionLine::factory()->create(['code' => 'PL-A', 'name' => 'A 线']);
    $lineB = ProductionLine::factory()->create(['code' => 'PL-B', 'name' => 'B 线']);
    ProductionLine::factory()->create(['code' => 'PL-C', 'name' => 'C 线']);

    [, $taskA, $operationA] = dashboardProductionReferences(line: $lineA);
    $orderB = ProductionOrder::factory()->create([
        'production_line_id' => $lineB->id,
        'status' => ProductionOrder::IN_PROGRESS,
    ]);
    [, $taskB, $operationB] = dashboardProductionReferences($orderB);

    foreach ([
        [$taskA, $operationA, 3, ProductionReport::NORMAL],
        [$taskA, $operationA, 2, ProductionReport::NORMAL],
        [$taskA, $operationA, 10, ProductionReport::REVERSED],
        [$taskB, $operationB, 4, ProductionReport::NORMAL],
    ] as [$task, $operation, $quantity, $type]) {
        ProductionReport::factory()->create([
            'production_task_id' => $task->id,
            'route_operation_id' => $operation->id,
            'quantity' => $quantity,
            'qualified_quantity' => $quantity,
            'defective_quantity' => 0,
            'type' => $type,
        ]);
    }

    $actor = userWithPermissions(['dashboard.trend']);

    $items = bearer($actor)->getJson('/api/v1/dashboard/production-lines')
        ->assertOk()
        ->assertJsonCount(3, 'data.items')
        ->json('data.items');

    expect($items[0]['code'])->toBe('PL-A')
        ->and($items[0]['name'])->toBe('A 线')
        ->and($items[0]['quantity'])->toBe('5.000')
        ->and($items[1]['code'])->toBe('PL-B')
        ->and($items[1]['quantity'])->toBe('4.000')
        ->and($items[2]['code'])->toBe('PL-C')
        ->and($items[2]['quantity'])->toBe('0.000');
});

it('returns the qualified rate and defect reason distribution from valid reports', function () {
    [, $task, $operation] = dashboardProductionReferences();

    ProductionReport::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'quantity' => 100,
        'qualified_quantity' => 80,
        'defective_quantity' => 20,
    ]);
    ProductionReport::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'quantity' => 100,
        'qualified_quantity' => 50,
        'defective_quantity' => 50,
        'type' => ProductionReport::REVERSED,
    ]);

    $scratch = DefectCode::factory()->create(['code' => 'DEF-SCRATCH', 'name' => '表面划伤', 'category' => '外观']);
    $size = DefectCode::factory()->create(['code' => 'DEF-SIZE', 'name' => '尺寸超差', 'category' => '尺寸']);

    foreach ([
        [$scratch, 6],
        [$scratch, 2],
        [$size, 5],
    ] as [$defectCode, $quantity]) {
        DefectiveProduct::factory()->create([
            'defect_code_id' => $defectCode->id,
            'quantity' => $quantity,
        ]);
    }

    $actor = userWithPermissions(['dashboard.quality']);

    bearer($actor)->getJson('/api/v1/dashboard/quality')
        ->assertOk()
        ->assertJsonPath('data.qualified_quantity', '80.000')
        ->assertJsonPath('data.defective_quantity', '20.000')
        ->assertJsonPath('data.total_quantity', '100.000')
        ->assertJsonPath('data.qualified_rate', '80.00')
        ->assertJsonCount(2, 'data.defect_reasons')
        ->assertJsonPath('data.defect_reasons.0.code', 'DEF-SCRATCH')
        ->assertJsonPath('data.defect_reasons.0.name', '表面划伤')
        ->assertJsonPath('data.defect_reasons.0.quantity', '8.000')
        ->assertJsonPath('data.defect_reasons.1.code', 'DEF-SIZE')
        ->assertJsonPath('data.defect_reasons.1.quantity', '5.000');
});

it('returns a zero qualified rate when there are no valid reports', function () {
    $actor = userWithPermissions(['dashboard.quality']);

    bearer($actor)->getJson('/api/v1/dashboard/quality')
        ->assertOk()
        ->assertJsonPath('data.qualified_quantity', '0.000')
        ->assertJsonPath('data.defective_quantity', '0.000')
        ->assertJsonPath('data.total_quantity', '0.000')
        ->assertJsonPath('data.qualified_rate', '0.00')
        ->assertJsonCount(0, 'data.defect_reasons');
});

it('returns zero-filled equipment status counts without disabled equipment', function () {
    $line = ProductionLine::factory()->create();

    Equipment::factory()->count(2)->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
        'status' => Equipment::RUNNING,
    ]);
    Equipment::factory()->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
        'status' => Equipment::IDLE,
    ]);
    Equipment::factory()->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
        'status' => Equipment::FAULT,
    ]);
    Equipment::factory()->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
        'status' => Equipment::OFFLINE,
    ]);
    Equipment::factory()->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
        'status' => Equipment::RUNNING,
        'enabled' => false,
    ]);

    $actor = userWithPermissions(['dashboard.equipment']);

    bearer($actor)->getJson('/api/v1/dashboard/equipment-status')
        ->assertOk()
        ->assertJsonCount(5, 'data.items')
        ->assertJsonPath('data.items.0.status', Equipment::RUNNING)
        ->assertJsonPath('data.items.0.label', '运行')
        ->assertJsonPath('data.items.0.count', 2)
        ->assertJsonPath('data.items.1.status', Equipment::IDLE)
        ->assertJsonPath('data.items.1.count', 1)
        ->assertJsonPath('data.items.2.status', Equipment::FAULT)
        ->assertJsonPath('data.items.2.count', 1)
        ->assertJsonPath('data.items.3.status', Equipment::REPAIR)
        ->assertJsonPath('data.items.3.count', 0)
        ->assertJsonPath('data.items.4.status', Equipment::OFFLINE)
        ->assertJsonPath('data.items.4.count', 1);
});

it('returns the latest valid orders, reports, exceptions and alarms', function () {
    $line = ProductionLine::factory()->create();

    ProductionOrder::factory()->create([
        'code' => 'PO-LATEST-OLD',
        'production_line_id' => $line->id,
        'status' => ProductionOrder::IN_PROGRESS,
        'created_at' => '2026-11-16 08:00:00',
    ]);
    $latestOrder = ProductionOrder::factory()->create([
        'code' => 'PO-LATEST-NEW',
        'production_line_id' => $line->id,
        'status' => ProductionOrder::IN_PROGRESS,
        'created_at' => '2026-11-16 09:00:00',
    ]);
    ProductionOrder::factory()->create([
        'code' => 'PO-LATEST-CANCELLED',
        'production_line_id' => $line->id,
        'status' => ProductionOrder::CANCELLED,
        'created_at' => '2026-11-16 10:00:00',
    ]);

    [, $task, $operation] = dashboardProductionReferences($latestOrder);
    $latestReport = ProductionReport::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'code' => 'PR-LATEST-VALID',
        'reported_at' => '2026-11-16 09:00:00',
    ]);
    ProductionReport::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'code' => 'PR-LATEST-REVERSED',
        'type' => ProductionReport::REVERSED,
        'reported_at' => '2026-11-16 10:00:00',
    ]);

    ProductionException::factory()->create([
        'code' => 'EXC-LATEST-OLD',
        'production_order_id' => $latestOrder->id,
        'reported_at' => '2026-11-16 09:00:00',
    ]);
    $latestException = ProductionException::factory()->create([
        'code' => 'EXC-LATEST-NEW',
        'production_order_id' => $latestOrder->id,
        'reported_at' => '2026-11-16 10:00:00',
    ]);

    $equipment = Equipment::factory()->create([
        'production_line_id' => $line->id,
        'workshop_id' => $line->workshop_id,
    ]);
    EquipmentAlarm::factory()->create([
        'equipment_id' => $equipment->id,
        'code' => 'ALM-LATEST-OLD',
        'triggered_at' => '2026-11-16 09:00:00',
    ]);
    $latestAlarm = EquipmentAlarm::factory()->create([
        'equipment_id' => $equipment->id,
        'code' => 'ALM-LATEST-NEW',
        'triggered_at' => '2026-11-16 10:00:00',
    ]);

    $actor = userWithPermissions(['dashboard.overview']);

    bearer($actor)->getJson('/api/v1/dashboard/latest-orders?limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $latestOrder->id)
        ->assertJsonPath('data.items.0.code', 'PO-LATEST-NEW')
        ->assertJsonPath('data.items.0.status_label', '生产中');

    bearer($actor)->getJson('/api/v1/dashboard/latest-reports?limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $latestReport->id)
        ->assertJsonPath('data.items.0.code', 'PR-LATEST-VALID')
        ->assertJsonPath('data.items.0.production_task.id', $task->id);

    bearer($actor)->getJson('/api/v1/dashboard/latest-exceptions?limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $latestException->id)
        ->assertJsonPath('data.items.0.code', 'EXC-LATEST-NEW');

    bearer($actor)->getJson('/api/v1/dashboard/latest-alarms?limit=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $latestAlarm->id)
        ->assertJsonPath('data.items.0.code', 'ALM-LATEST-NEW')
        ->assertJsonPath('data.items.0.equipment.code', $equipment->code);
});

it('requires authentication for every dashboard endpoint', function () {
    $endpoints = [
        '/today',
        '/production-trend',
        '/production-lines',
        '/quality',
        '/equipment-status',
        '/latest-orders',
        '/latest-reports',
        '/latest-exceptions',
        '/latest-alarms',
    ];

    foreach ($endpoints as $endpoint) {
        $this->getJson("/api/v1/dashboard{$endpoint}")->assertUnauthorized();
    }
});

it('rejects actors without the matching dashboard permissions', function () {
    $endpoints = [
        '/today',
        '/production-trend',
        '/production-lines',
        '/quality',
        '/equipment-status',
        '/latest-orders',
        '/latest-reports',
        '/latest-exceptions',
        '/latest-alarms',
    ];
    $actor = userWithPermissions([]);

    foreach ($endpoints as $endpoint) {
        bearer($actor)->getJson("/api/v1/dashboard{$endpoint}")->assertForbidden();
    }
});

it('validates the trend range and latest list limit', function () {
    $actor = userWithPermissions(['dashboard.trend', 'dashboard.overview']);

    bearer($actor)->getJson('/api/v1/dashboard/production-trend?days=0')->assertUnprocessable();
    bearer($actor)->getJson('/api/v1/dashboard/production-trend?days=32')->assertUnprocessable();
    bearer($actor)->getJson('/api/v1/dashboard/latest-orders?limit=0')->assertUnprocessable();
    bearer($actor)->getJson('/api/v1/dashboard/latest-orders?limit=21')->assertUnprocessable();
});
