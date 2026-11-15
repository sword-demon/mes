<?php

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryAlert;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Material\Model\Material;

it('requires permission to list inventory alerts', function () {
    bearer(userWithPermissions([], 'inventory_alert_view_denied'))
        ->getJson('/api/v1/inventory/alerts')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('requires authentication to list inventory alerts', function () {
    $this->getJson('/api/v1/inventory/alerts')
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
});

it('lists inventory alerts filtered by material', function () {
    $actor = userWithPermissions(['inventory.alerts.index'], 'inventory_alert_viewer');
    $material = Material::factory()->create(['code' => 'M001', 'min_stock' => 20]);
    $otherMaterial = Material::factory()->create(['code' => 'M002', 'min_stock' => 10]);
    InventoryAlert::factory()->create(['material_id' => $material->id, 'current_quantity' => 5, 'min_stock' => 20]);
    InventoryAlert::factory()->create(['material_id' => $otherMaterial->id, 'current_quantity' => 3, 'min_stock' => 10]);

    bearer($actor)->getJson('/api/v1/inventory/alerts?material_id='.$material->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.material.code', 'M001')
        ->assertJsonPath('data.items.0.current_quantity', 5)
        ->assertJsonPath('data.items.0.min_stock', 20)
        ->assertJsonPath('data.items.0.status', InventoryAlert::PENDING);
});

it('allows an alert reader to load the material options used by the alert list', function () {
    bearer(userWithPermissions(['inventory.alerts.index'], 'inventory_alert_options_reader'))
        ->getJson('/api/v1/inventory/alerts/options/materials?keyword=M001')
        ->assertOk()
        ->assertJsonStructure(['data' => ['materials']]);
});

it('searches materials beyond the default options limit for the alert filter', function () {
    Material::factory()->count(200)->create();
    $target = Material::factory()->create(['code' => 'MATERIAL-TAIL', 'name' => '尾部物料']);

    $response = bearer(userWithPermissions(['inventory.alerts.index'], 'inventory_alert_tail_search'))
        ->getJson('/api/v1/inventory/alerts/options/materials?keyword='.rawurlencode('尾部物料'));

    expect($response->json())->toBe([
        'code' => 200,
        'message' => 'success',
        'data' => ['materials' => [[
            'id' => $target->id,
            'code' => 'MATERIAL-TAIL',
            'name' => '尾部物料',
            'unit' => $target->unit,
            'batch_managed' => $target->batch_managed,
        ]]],
        'timestamp' => $response->json('timestamp'),
    ]);
});

it('validates the inventory alert material filter', function (string $query) {
    bearer(userWithPermissions(['inventory.alerts.index'], 'inventory_alert_invalid_filter_'.$query))
        ->getJson('/api/v1/inventory/alerts?'.$query)
        ->assertUnprocessable()
        ->assertJsonPath('code', 422)
        ->assertJsonPath('data.errors.material_id.0', 'The material id field must be an integer.');
})->with(['material_id[]=1', 'material_id=abc']);

it('allows an authorized warehouse operator to confirm and close an alert', function () {
    $actor = userWithPermissions(
        ['inventory.alerts.index', 'inventory.alerts.confirm', 'inventory.alerts.close'],
        'inventory_alert_handler',
    );
    $material = Material::factory()->create(['min_stock' => 20]);
    $alert = InventoryAlert::factory()->create([
        'material_id' => $material->id,
        'status' => InventoryAlert::PENDING,
        'current_quantity' => 5,
        'min_stock' => 20,
    ]);

    bearer($actor)->postJson('/api/v1/inventory/alerts/'.$alert->id.'/confirm')
        ->assertOk()
        ->assertJsonPath('data.status', InventoryAlert::CONFIRMED);

    bearer($actor)->postJson('/api/v1/inventory/alerts/'.$alert->id.'/close')
        ->assertOk()
        ->assertJsonPath('data.status', InventoryAlert::CLOSED);
});

it('closes the alert against replenished stock and creates a new alert after stock drops again', function () {
    $actor = userWithPermissions(['inventory.alerts.close'], 'inventory_alert_replenish_handler');
    $material = Material::factory()->create(['min_stock' => 20]);
    $warehouse = Warehouse::factory()->create();
    $location = StorageLocation::factory()->create(['warehouse_id' => $warehouse->id]);
    $stock = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 5,
    ]);
    $alert = InventoryAlert::factory()->create([
        'material_id' => $material->id,
        'current_quantity' => 5,
        'min_stock' => 20,
    ]);

    $stock->update(['current_quantity' => 25]);
    $response = bearer($actor)->postJson('/api/v1/inventory/alerts/'.$alert->id.'/close')
        ->assertOk()
        ->assertJsonPath('data.current_quantity', 25);

    expect($response->json('data.resolved_at'))->not->toBeNull();

    $stock->update(['current_quantity' => 4]);
    $this->artisan('inventory:scan-alerts')->assertSuccessful();
    expect(InventoryAlert::query()->where('material_id', $material->id)->count())->toBe(2);
});

it('rejects confirming or closing an already closed alert', function () {
    $actor = userWithPermissions(
        ['inventory.alerts.confirm', 'inventory.alerts.close'],
        'inventory_alert_closed_handler',
    );
    $alert = InventoryAlert::factory()->create([
        'status' => InventoryAlert::CLOSED,
        'resolved_at' => now(),
    ]);

    bearer($actor)->postJson('/api/v1/inventory/alerts/'.$alert->id.'/confirm')
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有待确认的库存预警可以确认');

    bearer($actor)->postJson('/api/v1/inventory/alerts/'.$alert->id.'/close')
        ->assertUnprocessable()
        ->assertJsonPath('message', '库存预警已关闭');
});

it('does not allow closing an inventory alert without permission', function () {
    $material = Material::factory()->create();
    $alert = InventoryAlert::factory()->create(['material_id' => $material->id]);

    bearer(userWithPermissions([], 'inventory_alert_close_denied'))
        ->postJson('/api/v1/inventory/alerts/'.$alert->id.'/close')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});
