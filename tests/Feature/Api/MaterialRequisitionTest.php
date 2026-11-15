<?php

use App\Modules\Bom\Model\BomItem;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Production\Model\ProductionOrder;

it('suggests the remaining requisition quantity from the order BOM', function () {
    $actor = userWithPermissions(['material.requisitions.create']);
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 10,
    ]);
    $material = Material::factory()->create(['code' => 'M001', 'name' => '冷轧钢板']);
    BomItem::factory()->create([
        'bom_id' => $order->bom_id,
        'material_id' => $material->id,
        'quantity' => 2,
        'loss_rate' => 5,
    ]);

    bearer($actor)->getJson('/api/v1/materials/requisitions/suggestions?'.http_build_query([
        'production_order_id' => $order->id,
    ]))->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.material.id', $material->id)
        ->assertJsonPath('data.0.material.code', 'M001')
        ->assertJsonPath('data.0.required_quantity', 21)
        ->assertJsonPath('data.0.issued_quantity', 0)
        ->assertJsonPath('data.0.suggested_quantity', 21);
});

it('creates a requisition linked to the production order and material', function () {
    $actor = userWithPermissions(['material.requisitions.create']);
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::RELEASED]);
    $material = Material::factory()->create();

    bearer($actor)->postJson('/api/v1/materials/requisitions', [
        'production_order_id' => $order->id,
        'items' => [[
            'material_id' => $material->id,
            'requested_quantity' => 3.5,
        ]],
        'remark' => '首批领料',
    ])->assertOk()
        ->assertJsonPath('data.production_order_id', $order->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.items.0.material_id', $material->id)
        ->assertJsonPath('data.items.0.requested_quantity', 3.5)
        ->assertJsonPath('data.items.0.issued_quantity', 0);

    $this->assertDatabaseHas('mes_material_requisitions', [
        'production_order_id' => $order->id,
        'status' => 'pending',
        'remark' => '首批领料',
        'created_by_id' => $actor->id,
    ]);
    $this->assertDatabaseHas('mes_material_requisition_items', [
        'material_id' => $material->id,
        'requested_quantity' => 3.5,
        'issued_quantity' => 0,
    ]);
});

it('issues a requisition, reduces available stock and writes a ledger row', function () {
    $actor = userWithPermissions(['material.requisitions.issue']);
    [, $location] = inventoryLocation();
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::RELEASED, 'code' => 'PO-100001']);
    $material = Material::factory()->create(['batch_managed' => true]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => 'B001',
        'current_quantity' => 10,
        'frozen_quantity' => 1,
    ]);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-100001',
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 4,
    ]);

    bearer($actor)->postJson("/api/v1/materials/requisitions/{$requisition->id}/issue", [
        'items' => [[
            'id' => $item->id,
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->id,
            'batch_no' => 'B001',
            'quantity' => 4,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.items.0.issued_quantity', 4);

    expect((float) $inventory->refresh()->current_quantity)->toBe(6.0)
        ->and((float) $item->refresh()->issued_quantity)->toBe(4.0);

    $this->assertDatabaseHas('mes_inventory_transactions', [
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -4,
        'before_quantity' => 10,
        'after_quantity' => 6,
        'reference_no' => 'MR-100001',
        'created_by_id' => $actor->id,
    ]);
});

it('refuses to issue more than the available stock and changes nothing', function () {
    $actor = userWithPermissions(['material.requisitions.issue']);
    [, $location] = inventoryLocation();
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::RELEASED]);
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => '',
        'current_quantity' => 5,
        'frozen_quantity' => 2,
    ]);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-100002',
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 4,
    ]);

    bearer($actor)->postJson("/api/v1/materials/requisitions/{$requisition->id}/issue", [
        'items' => [[
            'id' => $item->id,
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->id,
            'quantity' => 4,
        ]],
    ])->assertStatus(422)
        ->assertJsonPath('message', '出库数量超过可用库存');

    expect((float) $inventory->refresh()->current_quantity)->toBe(5.0)
        ->and((float) $item->refresh()->issued_quantity)->toBe(0.0)
        ->and($requisition->refresh()->status)->toBe('pending')
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('warns when issued quantity exceeds the BOM requirement but still issues', function () {
    $actor = userWithPermissions(['material.requisitions.create', 'material.requisitions.issue']);
    [, $location] = inventoryLocation();
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 10,
    ]);
    $material = Material::factory()->create(['batch_managed' => false]);
    BomItem::factory()->create([
        'bom_id' => $order->bom_id,
        'material_id' => $material->id,
        'quantity' => 1,
        'loss_rate' => 0,
    ]);
    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => '',
        'current_quantity' => 20,
    ]);

    $created = bearer($actor)->postJson('/api/v1/materials/requisitions', [
        'production_order_id' => $order->id,
        'items' => [['material_id' => $material->id, 'requested_quantity' => 12]],
    ])->assertOk()->json('data');

    bearer($actor)->postJson("/api/v1/materials/requisitions/{$created['id']}/issue", [
        'items' => [[
            'id' => $created['items'][0]['id'],
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->id,
            'quantity' => 12,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.status', 'issued')
        ->assertJsonPath('data.items.0.over_requirement', true);
});

it('filters requisitions by order, material and date', function () {
    $actor = userWithPermissions(['material.requisitions.index']);
    $order = ProductionOrder::factory()->create();
    $otherOrder = ProductionOrder::factory()->create();
    $material = Material::factory()->create();
    $otherMaterial = Material::factory()->create();
    $match = MaterialRequisition::query()->create([
        'code' => 'MR-200001',
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
        'created_at' => '2026-11-15 09:00:00',
    ]);
    $match->items()->create(['material_id' => $material->id, 'requested_quantity' => 1]);
    $other = MaterialRequisition::query()->create([
        'code' => 'MR-200002',
        'production_order_id' => $otherOrder->id,
        'created_by_id' => $actor->id,
        'created_at' => '2026-11-14 09:00:00',
    ]);
    $other->items()->create(['material_id' => $otherMaterial->id, 'requested_quantity' => 1]);

    bearer($actor)->getJson('/api/v1/materials/requisitions?'.http_build_query([
        'production_order_id' => $order->id,
        'material_id' => $material->id,
        'date_from' => '2026-11-15',
        'date_to' => '2026-11-15',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'MR-200001')
        ->assertJsonPath('data.items.0.production_order.code', $order->code)
        ->assertJsonPath('data.items.0.items.0.material.code', $material->code);
});

it('rejects a requisition for an order that is not ready to consume material', function () {
    $actor = userWithPermissions(['material.requisitions.create']);
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::DRAFT]);
    $material = Material::factory()->create();

    bearer($actor)->postJson('/api/v1/materials/requisitions', [
        'production_order_id' => $order->id,
        'items' => [['material_id' => $material->id, 'requested_quantity' => 1]],
    ])->assertStatus(422)
        ->assertJsonPath('message', '生产订单当前状态为「草稿」，不能领料');

    $this->assertDatabaseCount('mes_material_requisitions', 0);
});

it('rejects issuing a requisition item twice', function () {
    $actor = userWithPermissions(['material.requisitions.issue']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => '',
        'current_quantity' => 5,
    ]);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-300001',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);

    bearer($actor)->postJson("/api/v1/materials/requisitions/{$requisition->id}/issue", [
        'items' => [[
            'id' => $item->id,
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->id,
            'quantity' => 1,
        ]],
    ])->assertStatus(422)
        ->assertJsonPath('message', '领料单已发料');

    expect(InventoryTransaction::query()->count())->toBe(0);
});

it('requires the matching requisition permission', function (string $method, string $uri) {
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-DENIED',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => userWithPermissions([])->id,
    ]);
    $uri = str_replace('{id}', (string) $requisition->id, $uri);

    bearer(userWithPermissions([], 'requisition_denied_'.$method))->json($method, $uri)
        ->assertForbidden();
})->with([
    ['GET', '/api/v1/materials/requisitions'],
    ['GET', '/api/v1/materials/requisitions/suggestions'],
    ['POST', '/api/v1/materials/requisitions'],
    ['POST', '/api/v1/materials/requisitions/{id}/issue'],
]);
