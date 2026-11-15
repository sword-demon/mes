<?php

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialReturn;
use App\Modules\Production\Model\ProductionOrder;

it('creates a pending return linked to the requisition and production order without touching stock', function () {
    $actor = userWithPermissions(['material.returns.create']);
    [, $location] = inventoryLocation();
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::RELEASED]);
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => '',
        'current_quantity' => 10,
    ]);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600001',
        'production_order_id' => $order->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);

    bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $requisition->id,
        'items' => [['requisition_item_id' => $item->id, 'quantity' => 2]],
        'remark' => '余料退回',
    ])->assertOk()
        ->assertJsonPath('data.production_order_id', $order->id)
        ->assertJsonPath('data.requisition_id', $requisition->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.items.0.requisition_item_id', $item->id)
        ->assertJsonPath('data.items.0.material.id', $material->id)
        ->assertJsonPath('data.items.0.quantity', 2);

    $this->assertDatabaseHas('mes_material_returns', [
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'status' => 'pending',
        'remark' => '余料退回',
        'created_by_id' => $actor->id,
    ]);
    $this->assertDatabaseHas('mes_material_return_items', [
        'requisition_item_id' => $item->id,
        'quantity' => 2,
    ]);

    expect((float) $inventory->refresh()->current_quantity)->toBe(10.0)
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('rejects a return beyond the issued quantity, counting earlier returns', function () {
    $actor = userWithPermissions(['material.returns.create']);
    $order = ProductionOrder::factory()->create();
    $material = Material::factory()->create();
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600002',
        'production_order_id' => $order->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);
    $return = MaterialReturn::query()->create([
        'code' => 'RT-600001',
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'status' => MaterialReturn::APPROVED,
        'created_by_id' => $actor->id,
    ]);
    $return->items()->create([
        'requisition_item_id' => $item->id,
        'quantity' => 3,
    ]);

    bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $requisition->id,
        'items' => [['requisition_item_id' => $item->id, 'quantity' => 3]],
    ])->assertStatus(422)
        ->assertJsonPath('message', '退料数量超过可退数量');

    expect(MaterialReturn::query()->count())->toBe(1);
});

it('approves a return, restores stock and writes a ledger row', function () {
    $actor = userWithPermissions(['material.returns.audit']);
    [, $location] = inventoryLocation();
    $order = ProductionOrder::factory()->create(['code' => 'PO-600001']);
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => '',
        'current_quantity' => 10,
        'frozen_quantity' => 0,
    ]);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600003',
        'production_order_id' => $order->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);
    $return = MaterialReturn::query()->create([
        'code' => 'RT-600002',
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $returnItem = $return->items()->create([
        'requisition_item_id' => $item->id,
        'quantity' => 2,
    ]);

    bearer($actor)->postJson("/api/v1/materials/returns/{$return->id}/approve", [
        'items' => [[
            'id' => $returnItem->id,
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->id,
            'batch_no' => null,
        ]],
    ])->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by.id', $actor->id);

    expect((float) $inventory->refresh()->current_quantity)->toBe(12.0);

    $this->assertDatabaseHas('mes_inventory_transactions', [
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::INBOUND,
        'quantity' => 2,
        'before_quantity' => 10,
        'after_quantity' => 12,
        'reference_no' => 'RT-600002',
        'remark' => '生产退料',
        'created_by_id' => $actor->id,
    ]);
});

it('rejects approving the same return twice and never restores stock twice', function () {
    $actor = userWithPermissions(['material.returns.audit']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => '',
        'current_quantity' => 10,
    ]);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600004',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);
    $return = MaterialReturn::query()->create([
        'code' => 'RT-600003',
        'requisition_id' => $requisition->id,
        'production_order_id' => $requisition->production_order_id,
        'created_by_id' => $actor->id,
    ]);
    $returnItem = $return->items()->create([
        'requisition_item_id' => $item->id,
        'quantity' => 1,
    ]);
    $payload = [
        'items' => [[
            'id' => $returnItem->id,
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->id,
        ]],
    ];

    bearer($actor)->postJson("/api/v1/materials/returns/{$return->id}/approve", $payload)->assertOk();

    bearer($actor)->postJson("/api/v1/materials/returns/{$return->id}/approve", $payload)
        ->assertStatus(422)
        ->assertJsonPath('message', '退料单已审核');

    expect((float) $inventory->refresh()->current_quantity)->toBe(11.0)
        ->and(InventoryTransaction::query()->count())->toBe(1);
});

it('requires a destination for every return item before approving', function () {
    $actor = userWithPermissions(['material.returns.audit']);
    [, $location] = inventoryLocation();
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600012',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $firstItem = $requisition->items()->create([
        'material_id' => Material::factory()->create()->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);
    $secondItem = $requisition->items()->create([
        'material_id' => Material::factory()->create()->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);
    $return = MaterialReturn::query()->create([
        'code' => 'RT-600008',
        'requisition_id' => $requisition->id,
        'production_order_id' => $requisition->production_order_id,
        'created_by_id' => $actor->id,
    ]);
    $firstReturnItem = $return->items()->create([
        'requisition_item_id' => $firstItem->id,
        'quantity' => 1,
    ]);
    $return->items()->create([
        'requisition_item_id' => $secondItem->id,
        'quantity' => 1,
    ]);

    bearer($actor)->postJson("/api/v1/materials/returns/{$return->id}/approve", [
        'items' => [[
            'id' => $firstReturnItem->id,
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->id,
        ]],
    ])->assertStatus(422)
        ->assertJsonPath('message', '请为每条退料明细指定退回库位');

    expect($return->refresh()->status)->toBe('pending')
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('rejects approving a return item that belongs to another return', function () {
    $actor = userWithPermissions(['material.returns.audit']);
    [, $location] = inventoryLocation();
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600013',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $firstItem = $requisition->items()->create([
        'material_id' => Material::factory()->create()->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);
    $secondItem = $requisition->items()->create([
        'material_id' => Material::factory()->create()->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);
    $firstReturn = MaterialReturn::query()->create([
        'code' => 'RT-600009',
        'requisition_id' => $requisition->id,
        'production_order_id' => $requisition->production_order_id,
        'created_by_id' => $actor->id,
    ]);
    $firstReturn->items()->create(['requisition_item_id' => $firstItem->id, 'quantity' => 1]);
    $secondReturn = MaterialReturn::query()->create([
        'code' => 'RT-600010',
        'requisition_id' => $requisition->id,
        'production_order_id' => $requisition->production_order_id,
        'created_by_id' => $actor->id,
    ]);
    $foreignItem = $secondReturn->items()->create(['requisition_item_id' => $secondItem->id, 'quantity' => 1]);

    bearer($actor)->postJson("/api/v1/materials/returns/{$firstReturn->id}/approve", [
        'items' => [[
            'id' => $foreignItem->id,
            'warehouse_id' => $location->warehouse_id,
            'location_id' => $location->id,
        ]],
    ])->assertStatus(422)
        ->assertJsonPath('message', '退料明细不属于该退料单');

    expect($firstReturn->refresh()->status)->toBe('pending')
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('rolls back earlier lines when a later approval line fails', function () {
    $actor = userWithPermissions(['material.returns.audit']);
    [, $location] = inventoryLocation();
    $firstMaterial = Material::factory()->create(['batch_managed' => false]);
    $secondMaterial = Material::factory()->create(['batch_managed' => true]);
    $inventory = Inventory::factory()->create([
        'material_id' => $firstMaterial->id,
        'location_id' => $location->id,
        'batch_no' => '',
        'current_quantity' => 10,
    ]);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600014',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $firstItem = $requisition->items()->create([
        'material_id' => $firstMaterial->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);
    $secondItem = $requisition->items()->create([
        'material_id' => $secondMaterial->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);
    $return = MaterialReturn::query()->create([
        'code' => 'RT-600011',
        'requisition_id' => $requisition->id,
        'production_order_id' => $requisition->production_order_id,
        'created_by_id' => $actor->id,
    ]);
    $firstReturnItem = $return->items()->create(['requisition_item_id' => $firstItem->id, 'quantity' => 1]);
    $secondReturnItem = $return->items()->create(['requisition_item_id' => $secondItem->id, 'quantity' => 1]);

    bearer($actor)->postJson("/api/v1/materials/returns/{$return->id}/approve", [
        'items' => [
            [
                'id' => $firstReturnItem->id,
                'warehouse_id' => $location->warehouse_id,
                'location_id' => $location->id,
            ],
            [
                'id' => $secondReturnItem->id,
                'warehouse_id' => $location->warehouse_id,
                'location_id' => $location->id,
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonPath('message', '批次管理物料必须填写批次号');

    expect((float) $inventory->refresh()->current_quantity)->toBe(10.0)
        ->and($return->refresh()->status)->toBe('pending')
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('lists returnable quantities for a requisition after earlier returns', function () {
    $actor = userWithPermissions(['material.returns.create']);
    $order = ProductionOrder::factory()->create();
    $firstMaterial = Material::factory()->create(['code' => 'M001']);
    $secondMaterial = Material::factory()->create(['code' => 'M002']);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600005',
        'production_order_id' => $order->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $actor->id,
    ]);
    $firstItem = $requisition->items()->create([
        'material_id' => $firstMaterial->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);
    $secondItem = $requisition->items()->create([
        'material_id' => $secondMaterial->id,
        'requested_quantity' => 3,
        'issued_quantity' => 3,
    ]);
    $pending = MaterialReturn::query()->create([
        'code' => 'RT-600004',
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $pending->items()->create(['requisition_item_id' => $firstItem->id, 'quantity' => 2]);
    $partial = MaterialReturn::query()->create([
        'code' => 'RT-600006',
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'status' => MaterialReturn::APPROVED,
        'created_by_id' => $actor->id,
    ]);
    $partial->items()->create(['requisition_item_id' => $firstItem->id, 'quantity' => 1]);
    $approved = MaterialReturn::query()->create([
        'code' => 'RT-600005',
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'status' => MaterialReturn::APPROVED,
        'created_by_id' => $actor->id,
    ]);
    $approved->items()->create(['requisition_item_id' => $secondItem->id, 'quantity' => 3]);

    bearer($actor)->getJson('/api/v1/materials/returns/suggestions?requisition_id='.$requisition->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.requisition_item_id', $firstItem->id)
        ->assertJsonPath('data.0.material.code', 'M001')
        ->assertJsonPath('data.0.issued_quantity', 5)
        ->assertJsonPath('data.0.approved_returned_quantity', 1)
        ->assertJsonPath('data.0.pending_quantity', 2)
        ->assertJsonPath('data.0.returnable_quantity', 2);
});

it('counts pending returns against the returnable quantity', function () {
    $actor = userWithPermissions(['material.returns.create']);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600015',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => Material::factory()->create()->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);

    bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $requisition->id,
        'items' => [['requisition_item_id' => $item->id, 'quantity' => 3]],
    ])->assertOk();

    bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $requisition->id,
        'items' => [['requisition_item_id' => $item->id, 'quantity' => 3]],
    ])->assertStatus(422)
        ->assertJsonPath('message', '退料数量超过可退数量');
});

it('rechecks the approved total when approving an overbooked pending return', function () {
    $actor = userWithPermissions(['material.returns.create', 'material.returns.audit']);
    [$warehouse, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => '',
        'current_quantity' => 10,
    ]);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600008',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);
    $first = bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $requisition->id,
        'items' => [['requisition_item_id' => $item->id, 'quantity' => 3]],
    ])->assertOk()->json('data');

    bearer($actor)->postJson(
        "/api/v1/materials/returns/{$first['id']}/approve",
        [
            'items' => [[
                'id' => $first['items'][0]['id'],
                'warehouse_id' => $warehouse->id,
                'location_id' => $location->id,
            ]],
        ],
    )->assertOk();

    // Create now rejects this overbooking, so simulate a legacy/concurrent row.
    $second = MaterialReturn::query()->create([
        'code' => 'RT-600012',
        'requisition_id' => $requisition->id,
        'production_order_id' => $requisition->production_order_id,
        'created_by_id' => $actor->id,
    ]);
    $secondItem = $second->items()->create(['requisition_item_id' => $item->id, 'quantity' => 3]);

    bearer($actor)->postJson("/api/v1/materials/returns/{$second->id}/approve", [
        'items' => [[
            'id' => $secondItem->id,
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
        ]],
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', '退料数量超过可退数量');

    expect((float) $inventory->refresh()->current_quantity)->toBe(13.0)
        ->and(InventoryTransaction::query()->count())->toBe(1);
});

it('validates the return quantity', function (mixed $quantity, string $message) {
    $actor = userWithPermissions(['material.returns.create']);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600009',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => Material::factory()->create()->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);

    $response = bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $requisition->id,
        'items' => [['requisition_item_id' => $item->id, 'quantity' => $quantity]],
    ])->assertUnprocessable();

    expect($response->json('data.errors')['items.0.quantity'])->toBe([$message]);
})->with([
    [0, '退料数量必须大于 0'],
    [1.0001, '退料数量最多保留 3 位小数'],
]);

it('rejects a return item that belongs to another requisition', function () {
    $actor = userWithPermissions(['material.returns.create']);
    $first = MaterialRequisition::query()->create([
        'code' => 'MR-600010',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $second = MaterialRequisition::query()->create([
        'code' => 'MR-600011',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $foreignItem = $second->items()->create([
        'material_id' => Material::factory()->create()->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);

    bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $first->id,
        'items' => [['requisition_item_id' => $foreignItem->id, 'quantity' => 1]],
    ])->assertStatus(422)
        ->assertJsonPath('message', '退料明细不属于该领料单');

    expect(MaterialReturn::query()->count())->toBe(0);
});

it('filters returns by order, material and date', function () {
    $actor = userWithPermissions(['material.returns.index']);
    $order = ProductionOrder::factory()->create();
    $otherOrder = ProductionOrder::factory()->create();
    $material = Material::factory()->create(['code' => 'M001']);
    $otherMaterial = Material::factory()->create(['code' => 'M002']);
    $matchRequisition = MaterialRequisition::query()->create([
        'code' => 'MR-600006',
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $matchItem = $matchRequisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 3,
        'issued_quantity' => 3,
    ]);
    $match = MaterialReturn::query()->create([
        'code' => 'RT-600006',
        'requisition_id' => $matchRequisition->id,
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $match->forceFill(['created_at' => '2026-11-16 09:00:00'])->save();
    $match->items()->create(['requisition_item_id' => $matchItem->id, 'quantity' => 1]);
    $otherRequisition = MaterialRequisition::query()->create([
        'code' => 'MR-600007',
        'production_order_id' => $otherOrder->id,
        'created_by_id' => $actor->id,
    ]);
    $otherItem = $otherRequisition->items()->create([
        'material_id' => $otherMaterial->id,
        'requested_quantity' => 3,
        'issued_quantity' => 3,
    ]);
    $other = MaterialReturn::query()->create([
        'code' => 'RT-600007',
        'requisition_id' => $otherRequisition->id,
        'production_order_id' => $otherOrder->id,
        'created_by_id' => $actor->id,
    ]);
    $other->forceFill(['created_at' => '2026-11-15 09:00:00'])->save();
    $other->items()->create(['requisition_item_id' => $otherItem->id, 'quantity' => 1]);

    bearer($actor)->getJson('/api/v1/materials/returns?'.http_build_query([
        'production_order_id' => $order->id,
        'material_id' => $material->id,
        'date_from' => '2026-11-16',
        'date_to' => '2026-11-16',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'RT-600006')
        ->assertJsonPath('data.items.0.requisition.code', 'MR-600006')
        ->assertJsonPath('data.items.0.production_order.code', $order->code)
        ->assertJsonPath('data.items.0.items.0.material.code', 'M001');
});

it('cancels a pending return and releases its quota', function () {
    $actor = userWithPermissions(['material.returns.create']);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600016',
        'production_order_id' => ProductionOrder::factory()->create()->id,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => Material::factory()->create()->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);
    $created = bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $requisition->id,
        'items' => [['requisition_item_id' => $item->id, 'quantity' => 5]],
    ])->assertOk()->json('data');

    bearer($actor)->deleteJson("/api/v1/materials/returns/{$created['id']}")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.status_label', '已撤销');

    bearer($actor)->getJson('/api/v1/materials/returns/suggestions?requisition_id='.$requisition->id)
        ->assertOk()
        ->assertJsonPath('data.0.pending_quantity', 0)
        ->assertJsonPath('data.0.returnable_quantity', 5);

    bearer($actor)->postJson('/api/v1/materials/returns', [
        'requisition_id' => $requisition->id,
        'items' => [['requisition_item_id' => $item->id, 'quantity' => 5]],
    ])->assertOk();
});

it('rejects cancelling a return that is no longer pending', function () {
    $actor = userWithPermissions(['material.returns.create']);
    $order = ProductionOrder::factory()->create();
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600017',
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $return = MaterialReturn::query()->create([
        'code' => 'RT-600013',
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'status' => MaterialReturn::APPROVED,
        'created_by_id' => $actor->id,
    ]);

    bearer($actor)->deleteJson("/api/v1/materials/returns/{$return->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', '退料单不是待审核状态，不能撤销');
});

it('rejects cancelling another user return', function () {
    $creator = userWithPermissions([], 'return_creator');
    $actor = userWithPermissions(['material.returns.create']);
    $order = ProductionOrder::factory()->create();
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-600018',
        'production_order_id' => $order->id,
        'created_by_id' => $creator->id,
    ]);
    $return = MaterialReturn::query()->create([
        'code' => 'RT-600014',
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'created_by_id' => $creator->id,
    ]);

    bearer($actor)->deleteJson("/api/v1/materials/returns/{$return->id}")
        ->assertForbidden()
        ->assertJsonPath('message', '只能撤销自己创建的退料单');
});

it('requires the matching return permission', function (string $method, string $uri) {
    $actor = userWithPermissions([]);
    $order = ProductionOrder::factory()->create();
    $material = Material::factory()->create();
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-DENIED',
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $item = $requisition->items()->create([
        'material_id' => $material->id,
        'requested_quantity' => 1,
        'issued_quantity' => 1,
    ]);
    $return = MaterialReturn::query()->create([
        'code' => 'RT-DENIED',
        'requisition_id' => $requisition->id,
        'production_order_id' => $order->id,
        'created_by_id' => $actor->id,
    ]);
    $returnItem = $return->items()->create([
        'requisition_item_id' => $item->id,
        'quantity' => 1,
    ]);

    $uri = str_replace(
        ['{id}', '{requisition_id}'],
        [(string) $return->id, (string) $requisition->id],
        $uri,
    );

    bearer(userWithPermissions([], 'return_denied_'.$method))->json($method, $uri, [
        'requisition_id' => $requisition->id,
        'items' => [[
            'requisition_item_id' => $item->id,
            'quantity' => 1,
            'id' => $returnItem->id,
            'warehouse_id' => 1,
            'location_id' => 1,
        ]],
    ])->assertForbidden();
})->with([
    ['GET', '/api/v1/materials/returns'],
    ['GET', '/api/v1/materials/returns/suggestions?requisition_id={requisition_id}'],
    ['POST', '/api/v1/materials/returns'],
    ['POST', '/api/v1/materials/returns/{id}/approve'],
    ['DELETE', '/api/v1/materials/returns/{id}'],
]);

it('requires authentication for return endpoints', function (string $method, string $uri) {
    $this->json($method, str_replace('{id}', '1', $uri))
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
})->with([
    ['GET', '/api/v1/materials/returns'],
    ['GET', '/api/v1/materials/returns/suggestions?requisition_id=1'],
    ['POST', '/api/v1/materials/returns'],
    ['POST', '/api/v1/materials/returns/{id}/approve'],
    ['DELETE', '/api/v1/materials/returns/{id}'],
]);
