<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Organization\Model\Employee;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductSn;
use App\Modules\System\Model\User;

/**
 * @return array<int, string>
 */
function snPermissions(): array
{
    return [
        'production.sns.index',
        'production.sns.create',
        'production.sns.bind',
        'production.sns.print',
        'production.sns.void',
    ];
}

/**
 * @return array<int, string>
 */
function generateSns(User $actor, ProductionOrder $order, int $quantity, ?Employee $employee = null): array
{
    $payload = ['production_order_id' => $order->id, 'quantity' => $quantity];

    if ($employee !== null) {
        $payload['employee_id'] = $employee->id;
    }

    $response = bearer($actor)
        ->postJson('/api/v1/production/sns', $payload)
        ->assertOk();

    return collect($response->json('data.items'))->pluck('sn')->all();
}

it('generates serial numbers in the PRD daily format linked to the order, batch and operator', function () {
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 10,
    ]);
    $employee = Employee::factory()->create();
    $actor = userWithPermissions(snPermissions());

    $response = bearer($actor)->postJson('/api/v1/production/sns', [
        'production_order_id' => $order->id,
        'quantity' => 3,
        'employee_id' => $employee->id,
    ])->assertOk()
        ->assertJsonCount(3, 'data.items')
        ->assertJsonPath('data.batch.production_order_id', $order->id)
        ->assertJsonPath('data.items.0.production_order.id', $order->id)
        ->assertJsonPath('data.items.0.employee.id', $employee->id)
        ->assertJsonPath('data.items.0.status', ProductSn::ACTIVE);

    $sns = collect($response->json('data.items'))->pluck('sn');

    expect($sns)->toHaveCount(3)
        ->and($sns->unique())->toHaveCount(3)
        ->and(ProductSn::query()->count())->toBe(3)
        ->and(ProductionBatch::query()->count())->toBe(1);

    foreach ($sns as $sn) {
        expect($sn)->toMatch('/^MES\d{8}\d{6}$/');
    }
});

it('continues the daily serial sequence across generation batches', function () {
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 10,
    ]);

    $actor = userWithPermissions(snPermissions());
    $first = generateSns($actor, $order, 2);
    $second = generateSns($actor, $order, 1);

    $prefix = substr($first[0], 0, 11);
    $sequenceOf = static fn (string $sn): int => (int) substr($sn, 11);

    expect(substr($second[0], 0, 11))->toBe($prefix)
        ->and($sequenceOf($second[0]))->toBe($sequenceOf($first[1]) + 1)
        ->and(ProductionBatch::query()->count())->toBe(2);
});

it('refuses to generate serial numbers for a draft order or beyond the planned quantity', function () {
    $actor = userWithPermissions(snPermissions());
    $draft = ProductionOrder::factory()->create(['planned_quantity' => 10]);

    bearer($actor)->postJson('/api/v1/production/sns', [
        'production_order_id' => $draft->id,
        'quantity' => 1,
    ])->assertStatus(422)
        ->assertJsonPath('code', 422);

    foreach ([ProductionOrder::APPROVED, ProductionOrder::COMPLETED] as $status) {
        $unreleased = ProductionOrder::factory()->create([
            'status' => $status,
            'planned_quantity' => 10,
        ]);

        bearer($actor)->postJson('/api/v1/production/sns', [
            'production_order_id' => $unreleased->id,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    $released = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 3,
    ]);
    generateSns($actor, $released, 2);

    bearer($actor)->postJson('/api/v1/production/sns', [
        'production_order_id' => $released->id,
        'quantity' => 2,
    ])->assertStatus(422);

    expect(ProductSn::query()->count())->toBe(2);
});

it('binds a material batch, equipment and operator to a serial number only once', function () {
    $sn = ProductSn::factory()->create();
    $employee = Employee::factory()->create();
    $equipment = Equipment::factory()->create(['status' => Equipment::RUNNING]);
    $actor = userWithPermissions(snPermissions());

    bearer($actor)->putJson("/api/v1/production/sns/{$sn->sn}/bind", [
        'material_batch_no' => 'MAT-LOT-001',
        'equipment_id' => $equipment->id,
        'employee_id' => $employee->id,
    ])->assertOk()
        ->assertJsonPath('data.material_batch_no', 'MAT-LOT-001')
        ->assertJsonPath('data.equipment_id', $equipment->id)
        ->assertJsonPath('data.equipment.code', $equipment->code)
        ->assertJsonPath('data.employee.id', $employee->id);

    bearer($actor)->putJson("/api/v1/production/sns/{$sn->sn}/bind", [
        'material_batch_no' => 'MAT-LOT-002',
    ])->assertStatus(422)
        ->assertJsonPath('code', 422);

    bearer($actor)->putJson("/api/v1/production/sns/{$sn->sn}/bind", [
        'equipment_id' => $equipment->id,
    ])->assertStatus(422);

    bearer($actor)->putJson("/api/v1/production/sns/{$sn->sn}/bind", [
        'employee_id' => $employee->id,
    ])->assertStatus(422);

    expect($sn->fresh()->material_batch_no)->toBe('MAT-LOT-001');
});

it('refuses to bind a disabled operator', function () {
    $sn = ProductSn::factory()->create();
    $disabled = Employee::factory()->create(['status' => false]);
    $actor = userWithPermissions(snPermissions());

    bearer($actor)->putJson("/api/v1/production/sns/{$sn->sn}/bind", [
        'employee_id' => $disabled->id,
    ])->assertStatus(422)
        ->assertJsonPath('code', 422);
});

it('refuses to bind or generate with unavailable equipment', function () {
    $sn = ProductSn::factory()->create();
    $equipment = Equipment::factory()->create(['status' => Equipment::OFFLINE]);
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 2,
    ]);
    $actor = userWithPermissions(snPermissions());

    bearer($actor)->putJson("/api/v1/production/sns/{$sn->sn}/bind", [
        'equipment_id' => $equipment->id,
    ])->assertStatus(422)
        ->assertJsonPath('message', '设备不存在或当前不可用');

    bearer($actor)->postJson('/api/v1/production/sns', [
        'production_order_id' => $order->id,
        'quantity' => 1,
        'equipment_id' => $equipment->id,
    ])->assertStatus(422)
        ->assertJsonPath('message', '设备不存在或当前不可用');

    expect(ProductSn::query()->count())->toBe(1);
});

it('prints a label carrying the serial, product, order and batch', function () {
    $sn = ProductSn::factory()->create();
    $actor = userWithPermissions(snPermissions());
    $sn->load('productionOrder.product', 'productionBatch');

    bearer($actor)->getJson("/api/v1/production/sns/{$sn->sn}/label")
        ->assertOk()
        ->assertJsonPath('data.label.sn', $sn->sn)
        ->assertJsonPath('data.label.production_order', $sn->productionOrder->code)
        ->assertJsonPath('data.label.production_batch', $sn->productionBatch->code)
        ->assertJsonPath('data.label.product_name', $sn->productionOrder->product->name);

    expect($sn->fresh()->printed_at)->toBeNull();

    bearer($actor)->postJson("/api/v1/production/sns/{$sn->sn}/print")->assertOk();

    expect($sn->fresh()->printed_at)->not->toBeNull();
});

it('voids a serial number with a reason and blocks further binding, printing or voiding', function () {
    $sn = ProductSn::factory()->create();
    $equipment = Equipment::factory()->create(['status' => Equipment::RUNNING]);
    $actor = userWithPermissions(snPermissions());

    bearer($actor)->postJson("/api/v1/production/sns/{$sn->sn}/void", [
        'reason' => '产品报废',
    ])->assertOk()
        ->assertJsonPath('data.status', ProductSn::VOIDED)
        ->assertJsonPath('data.void_reason', '产品报废');

    bearer($actor)->postJson("/api/v1/production/sns/{$sn->sn}/void", [
        'reason' => '重复作废',
    ])->assertStatus(422);

    bearer($actor)->putJson("/api/v1/production/sns/{$sn->sn}/bind", [
        'equipment_id' => $equipment->id,
    ])->assertStatus(422);

    bearer($actor)->postJson("/api/v1/production/sns/{$sn->sn}/print")
        ->assertStatus(422);
});

it('lists serial numbers with filters and returns every operation status for one serial', function () {
    $sn = ProductSn::factory()->create();
    $other = ProductSn::factory()->create();
    $actor = userWithPermissions(snPermissions());

    ProcessRecord::factory()->create([
        'sn' => $sn->sn,
        'sequence' => 1,
        'status' => ProcessRecord::TRANSFERRED,
    ]);
    ProcessRecord::factory()->create([
        'sn' => $sn->sn,
        'sequence' => 2,
        'status' => ProcessRecord::PROCESSING,
    ]);

    bearer($actor)->getJson('/api/v1/production/sns?keyword='.$sn->sn)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.sn', $sn->sn);

    bearer($actor)->getJson("/api/v1/production/sns/{$sn->sn}")
        ->assertOk()
        ->assertJsonPath('data.sn', $sn->sn)
        ->assertJsonCount(2, 'data.process_records')
        ->assertJsonPath('data.process_records.0.status_label', '已流转')
        ->assertJsonPath('data.process_records.1.status_label', '加工中');

    bearer($actor)->getJson('/api/v1/production/sns?status=voided')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');

    expect($other->sn)->not->toBe($sn->sn);
});

it('requires the sn permission for every serial number endpoint', function () {
    $actor = userWithPermissions([]);
    $sn = ProductSn::factory()->create();

    $this->getJson('/api/v1/production/sns')->assertUnauthorized();

    bearer($actor)->getJson('/api/v1/production/sns')->assertForbidden();
    bearer($actor)->postJson('/api/v1/production/sns', [
        'production_order_id' => $sn->production_order_id,
        'quantity' => 1,
    ])->assertForbidden();
    bearer($actor)->postJson("/api/v1/production/sns/{$sn->sn}/void", [
        'reason' => '无权限',
    ])->assertForbidden();
});

it('validates the generated quantity and the void reason', function () {
    $actor = userWithPermissions(snPermissions());
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 10,
    ]);
    $sn = ProductSn::factory()->create();

    bearer($actor)->postJson('/api/v1/production/sns', [
        'production_order_id' => $order->id,
        'quantity' => 0,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('quantity', 'data.errors');

    bearer($actor)->postJson('/api/v1/production/sns', [
        'production_order_id' => $order->id,
        'quantity' => 1001,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('quantity', 'data.errors');

    bearer($actor)->postJson("/api/v1/production/sns/{$sn->sn}/void")
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason', 'data.errors');
});

it('offers only generatable orders and active employees in the form options', function () {
    $actor = userWithPermissions(snPermissions());
    $released = ProductionOrder::factory()->create(['status' => ProductionOrder::RELEASED]);
    ProductionOrder::factory()->create(['status' => ProductionOrder::APPROVED]);
    $active = Employee::factory()->create(['status' => true]);
    Employee::factory()->create(['status' => false]);

    bearer($actor)->getJson('/api/v1/production/sns/options')
        ->assertOk()
        ->assertJsonCount(1, 'data.orders')
        ->assertJsonPath('data.orders.0.id', $released->id)
        ->assertJsonCount(1, 'data.employees')
        ->assertJsonPath('data.employees.0.id', $active->id);
});
