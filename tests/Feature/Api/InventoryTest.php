<?php

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Material\Model\Material;

it('filters inventory by warehouse, location, material and batch and exposes available quantity', function () {
    $actor = userWithPermissions(['inventory.index']);
    [$warehouse, $location] = inventoryLocation();
    [, $otherLocation] = inventoryLocation('WH02', 'B-01');
    $material = Material::factory()->create(['code' => 'M001', 'name' => '冷轧钢板']);
    $other = Material::factory()->create(['code' => 'M002']);

    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => 'B20261101',
        'current_quantity' => 100,
        'frozen_quantity' => 10,
    ]);
    Inventory::factory()->create([
        'material_id' => $other->id,
        'location_id' => $otherLocation->id,
        'batch_no' => '',
        'current_quantity' => 50,
    ]);

    bearer($actor)->getJson('/api/v1/inventory?'.http_build_query([
        'warehouse_id' => $warehouse->id,
        'location_id' => $location->id,
        'material_id' => $material->id,
        'batch_no' => 'B20261101',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.location.code', 'A-01')
        ->assertJsonPath('data.items.0.location.warehouse.id', $warehouse->id)
        ->assertJsonPath('data.items.0.location.warehouse.name', '仓库 WH01')
        ->assertJsonPath('data.items.0.batch_no', 'B20261101')
        ->assertJsonPath('data.items.0.current_quantity', 100)
        ->assertJsonPath('data.items.0.frozen_quantity', 10)
        ->assertJsonPath('data.items.0.available_quantity', 90)
        ->assertJsonPath('data.items.0.material.code', 'M001');
});

it('searches inventory by material code', function () {
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['code' => 'M001', 'name' => '冷轧钢板']);
    Inventory::factory()->create(['material_id' => $material->id, 'location_id' => $location->id]);

    bearer(userWithPermissions(['inventory.index']))->getJson('/api/v1/inventory?keyword=M001')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.material.code', 'M001');
});

it('rejects the inventory list without permission', function () {
    bearer(userWithPermissions([], 'tester_denied'))->getJson('/api/v1/inventory')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('denies inventory access without a grant', function (string $endpoint) {
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    $payload = [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
        'quantity' => 10,
    ];
    $actor = userWithPermissions([], 'tester_'.$endpoint);

    $response = match ($endpoint) {
        'inbound' => bearer($actor)->postJson('/api/v1/inventory/inbound', $payload),
        'outbound' => bearer($actor)->postJson('/api/v1/inventory/outbound', $payload),
        'adjust' => bearer($actor)->postJson('/api/v1/inventory/adjust', [...$payload, 'current_quantity' => 10]),
        'transactions' => bearer($actor)->getJson('/api/v1/inventory/transactions'),
        'options' => bearer($actor)->getJson('/api/v1/inventory/options'),
    };

    $response->assertForbidden()->assertJsonPath('code', 403);
})->with(['inbound', 'outbound', 'adjust', 'transactions', 'options']);

it('serves inventory options with enabled materials and warehouse locations', function () {
    $actor = userWithPermissions(['inventory.inbound']);
    [$warehouse, $location] = inventoryLocation();
    $material = Material::factory()->create(['code' => 'M001', 'status' => true]);
    Material::factory()->create(['code' => 'M002', 'status' => false]);
    StorageLocation::factory()->create([
        'warehouse_id' => $warehouse->id,
        'code' => 'A-99',
        'status' => false,
    ]);

    bearer($actor)->getJson('/api/v1/inventory/options')
        ->assertOk()
        ->assertJsonCount(1, 'data.materials')
        ->assertJsonPath('data.materials.0.code', 'M001')
        ->assertJsonPath('data.materials.0.batch_managed', $material->batch_managed)
        ->assertJsonPath('data.warehouses.0.id', $warehouse->id)
        ->assertJsonCount(1, 'data.warehouses.0.locations')
        ->assertJsonPath('data.warehouses.0.locations.0.id', $location->id)
        ->assertJsonPath('data.warehouses.0.locations.0.code', 'A-01');
});

it('lets a movement-only user read the inventory ledger', function () {
    $actor = userWithPermissions(['inventory.inbound']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create();
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
    ]);
    InventoryTransaction::factory()->create([
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
    ]);

    bearer($actor)->getJson('/api/v1/inventory/transactions')
        ->assertOk()
        ->assertJsonCount(1, 'data.items');
});

it('creates an inventory row and an inbound transaction', function () {
    $actor = userWithPermissions(['inventory.inbound']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => true]);

    bearer($actor)->postJson('/api/v1/inventory/inbound', [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
        'batch_no' => 'B001',
        'quantity' => 120.5,
        'reference_no' => 'PO-2026-001',
        'remark' => '采购入库',
    ])->assertOk()
        ->assertJsonPath('data.current_quantity', 120.5)
        ->assertJsonPath('data.available_quantity', 120.5)
        ->assertJsonPath('data.frozen_quantity', 0)
        ->assertJsonPath('data.material.id', $material->id)
        ->assertJsonPath('data.location.code', 'A-01')
        ->assertJsonPath('data.location.warehouse.id', $location->warehouse_id);

    $inventory = Inventory::query()->where('material_id', $material->id)->firstOrFail();

    expect((float) $inventory->current_quantity)->toBe(120.5)
        ->and($inventory->location_id)->toBe($location->id)
        ->and($inventory->batch_no)->toBe('B001');

    $this->assertDatabaseHas('mes_inventory_transactions', [
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'created_by_id' => $actor->id,
        'business_type' => InventoryTransaction::INBOUND,
        'quantity' => 120.5,
        'before_quantity' => 0,
        'after_quantity' => 120.5,
        'reference_no' => 'PO-2026-001',
    ]);
});

it('accumulates repeated inbound and rejects a batch-managed material without a batch', function () {
    $actor = userWithPermissions(['inventory.inbound']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => true]);
    $payload = [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
        'batch_no' => 'B001',
        'quantity' => 100,
    ];

    bearer($actor)->postJson('/api/v1/inventory/inbound', $payload)->assertOk();
    bearer($actor)->postJson('/api/v1/inventory/inbound', [...$payload, 'quantity' => 50])->assertOk()
        ->assertJsonPath('data.current_quantity', 150);

    expect(InventoryTransaction::query()->count())->toBe(2)
        ->and((float) InventoryTransaction::query()->orderByDesc('id')->value('before_quantity'))->toBe(100.0)
        ->and((float) InventoryTransaction::query()->orderByDesc('id')->value('after_quantity'))->toBe(150.0);

    bearer($actor)->postJson('/api/v1/inventory/inbound', [...$payload, 'batch_no' => null])
        ->assertUnprocessable()
        ->assertJsonPath('message', '批次管理物料必须填写批次号');
});

it('rejects an inbound that would overflow the stock quantity column', function () {
    $actor = userWithPermissions(['inventory.inbound']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 999999999.999,
    ]);

    bearer($actor)->postJson('/api/v1/inventory/inbound', [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
        'quantity' => 1,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '库存数量超过上限');
});

it('rejects a disabled material and a location from another warehouse', function () {
    $actor = userWithPermissions(['inventory.inbound']);
    [$warehouse, $location] = inventoryLocation();
    [, $otherLocation] = inventoryLocation('WH02', 'B-01');
    $material = Material::factory()->create(['batch_managed' => false, 'status' => false]);
    $payload = [
        'material_id' => $material->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => $location->id,
        'quantity' => 10,
    ];

    bearer($actor)->postJson('/api/v1/inventory/inbound', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', '物料不存在或已停用');

    bearer($actor)->postJson('/api/v1/inventory/inbound', [
        ...$payload,
        'material_id' => Material::factory()->create(['batch_managed' => false])->id,
        'location_id' => $otherLocation->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '库位不存在、已停用或不属于所选仓库');
});

it('validates the required movement fields and quantity precision', function () {
    $actor = userWithPermissions(['inventory.inbound']);

    bearer($actor)->postJson('/api/v1/inventory/inbound', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['material_id', 'warehouse_id', 'location_id', 'quantity'], 'data.errors');

    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    $payload = [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
    ];

    bearer($actor)->postJson('/api/v1/inventory/inbound', [...$payload, 'quantity' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity', 'data.errors');
    bearer($actor)->postJson('/api/v1/inventory/inbound', [...$payload, 'quantity' => 1.2345])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity', 'data.errors');
});

it('rejects an outbound beyond available stock', function () {
    $actor = userWithPermissions(['inventory.outbound']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 100,
        'frozen_quantity' => 20,
    ]);
    $payload = [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
    ];

    bearer($actor)->postJson('/api/v1/inventory/outbound', [...$payload, 'quantity' => 80.001])
        ->assertUnprocessable()
        ->assertJsonPath('message', '出库数量超过可用库存');

    expect((float) $inventory->refresh()->current_quantity)->toBe(100.0)
        ->and(InventoryTransaction::query()->count())->toBe(0);
});

it('deducts stock up to the available limit', function () {
    $actor = userWithPermissions(['inventory.outbound']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 100,
        'frozen_quantity' => 20,
    ]);
    $payload = [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
    ];

    bearer($actor)->postJson('/api/v1/inventory/outbound', [...$payload, 'quantity' => 80])
        ->assertOk()
        ->assertJsonPath('data.current_quantity', 20)
        ->assertJsonPath('data.frozen_quantity', 20)
        ->assertJsonPath('data.available_quantity', 0);

    $this->assertDatabaseHas('mes_inventory_transactions', [
        'inventory_id' => $inventory->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -80,
        'before_quantity' => 100,
        'after_quantity' => 20,
    ]);
});

it('adjusts stock to a target', function () {
    $actor = userWithPermissions(['inventory.adjust']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 100,
        'frozen_quantity' => 20,
    ]);
    $payload = [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
        'batch_no' => $inventory->batch_no,
    ];

    bearer($actor)->postJson('/api/v1/inventory/adjust', [...$payload, 'current_quantity' => 60])
        ->assertOk()
        ->assertJsonPath('data.current_quantity', 60)
        ->assertJsonPath('data.available_quantity', 40);

    $this->assertDatabaseHas('mes_inventory_transactions', [
        'inventory_id' => $inventory->id,
        'business_type' => InventoryTransaction::ADJUSTMENT,
        'quantity' => -40,
        'before_quantity' => 100,
        'after_quantity' => 60,
    ]);
});

it('refuses an adjustment below the frozen quantity', function () {
    $actor = userWithPermissions(['inventory.adjust']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 100,
        'frozen_quantity' => 20,
    ]);
    $payload = [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
        'batch_no' => $inventory->batch_no,
    ];

    bearer($actor)->postJson('/api/v1/inventory/adjust', [...$payload, 'current_quantity' => 10])
        ->assertUnprocessable()
        ->assertJsonPath('message', '库存调整不能低于冻结数量');
});

it('rejects a stock adjustment that would not change the quantity', function () {
    $actor = userWithPermissions(['inventory.adjust']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 100,
    ]);

    bearer($actor)->postJson('/api/v1/inventory/adjust', [
        'material_id' => $material->id,
        'warehouse_id' => $location->warehouse_id,
        'location_id' => $location->id,
        'current_quantity' => 100,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '调整后库存与当前库存一致');
});

it('lists inventory transactions filtered by material', function () {
    $actor = userWithPermissions(['inventory.index']);
    [, $location] = inventoryLocation();
    $material = Material::factory()->create();
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 50,
    ]);
    InventoryTransaction::factory()->create([
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'created_by_id' => $actor->id,
        'business_type' => InventoryTransaction::INBOUND,
        'quantity' => 100,
        'before_quantity' => 0,
        'after_quantity' => 100,
        'reference_no' => 'PO-001',
    ]);
    InventoryTransaction::factory()->create([
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -50,
        'before_quantity' => 100,
        'after_quantity' => 50,
        'reference_no' => 'SO-001',
    ]);
    InventoryTransaction::factory()->create();

    bearer($actor)->getJson('/api/v1/inventory/transactions?'.http_build_query([
        'material_id' => $material->id,
    ]))->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.business_type', InventoryTransaction::INBOUND)
        ->assertJsonPath('data.items.0.quantity', 100)
        ->assertJsonPath('data.items.0.before_quantity', 0)
        ->assertJsonPath('data.items.0.after_quantity', 100)
        ->assertJsonPath('data.items.0.created_by.id', $actor->id)
        ->assertJsonPath('data.items.1.business_type', InventoryTransaction::OUTBOUND)
        ->assertJsonPath('data.items.1.quantity', -50)
        ->assertJsonPath('data.items.1.inventory.location.code', $location->code);
});

it('filters inventory transactions by warehouse, location and batch', function () {
    $actor = userWithPermissions(['inventory.index']);
    [$warehouse, $location] = inventoryLocation();
    [, $otherLocation] = inventoryLocation('WH02', 'B-01');
    $material = Material::factory()->create();

    foreach ([
        [$location, 'B001', 10],
        [$otherLocation, 'B002', 20],
    ] as [$target, $batchNo, $quantity]) {
        $inventory = Inventory::factory()->create([
            'material_id' => $material->id,
            'location_id' => $target->id,
            'batch_no' => $batchNo,
        ]);
        InventoryTransaction::factory()->create([
            'inventory_id' => $inventory->id,
            'material_id' => $material->id,
            'quantity' => $quantity,
            'after_quantity' => $quantity,
        ]);
    }

    bearer($actor)->getJson('/api/v1/inventory/transactions?'.http_build_query([
        'warehouse_id' => $warehouse->id,
        'location_id' => $location->id,
        'batch_no' => 'B001',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 10)
        ->assertJsonPath('data.items.0.inventory.batch_no', 'B001');
});
