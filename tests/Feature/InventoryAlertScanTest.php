<?php

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryAlert;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Material\Model\Material;
use Illuminate\Support\Facades\Artisan;

function inventoryAlertMaterial(string $code, float $minStock = 20, bool $active = true): Material
{
    return Material::factory()->create(['code' => $code, 'min_stock' => $minStock, 'status' => $active]);
}

function inventoryAlertPosition(string $code): StorageLocation
{
    $warehouse = Warehouse::factory()->create(['code' => $code]);

    return StorageLocation::factory()->create(['warehouse_id' => $warehouse->id]);
}

it('scans material-wide stock below the minimum and ignores equal or disabled materials', function () {
    $low = inventoryAlertMaterial('LOW');
    $equal = inventoryAlertMaterial('EQUAL');
    $disabled = inventoryAlertMaterial('OFF', active: false);
    $locationOne = inventoryAlertPosition('WH01');
    $locationTwo = inventoryAlertPosition('WH02');
    Inventory::factory()->create(['material_id' => $low->id, 'location_id' => $locationOne->id, 'batch_no' => 'B001', 'current_quantity' => 3]);
    Inventory::factory()->create(['material_id' => $low->id, 'location_id' => $locationTwo->id, 'batch_no' => 'B002', 'current_quantity' => 5]);
    Inventory::factory()->create(['material_id' => $equal->id, 'location_id' => $locationOne->id, 'current_quantity' => 20]);
    Inventory::factory()->create(['material_id' => $disabled->id, 'location_id' => $locationOne->id, 'current_quantity' => 1]);
    $existingAlert = InventoryAlert::factory()->create([
        'material_id' => $disabled->id,
        'status' => InventoryAlert::PENDING,
    ]);

    Artisan::call('inventory:scan-alerts');

    $this->assertDatabaseHas('mes_inventory_alerts', [
        'material_id' => $low->id,
        'min_stock' => '20.000',
        'current_quantity' => '8.000',
        'status' => InventoryAlert::PENDING,
    ]);
    $this->assertDatabaseMissing('mes_inventory_alerts', ['material_id' => $equal->id]);
    $this->assertDatabaseHas('mes_inventory_alerts', [
        'id' => $existingAlert->id,
        'status' => InventoryAlert::CLOSED,
        'active_key' => null,
    ]);
});

it('creates an alert for a material with no inventory records and updates active alerts on each scan', function () {
    $material = inventoryAlertMaterial('NO-STOCK');

    Artisan::call('inventory:scan-alerts');
    $alert = InventoryAlert::query()->where('material_id', $material->id)->firstOrFail();
    expect((float) $alert->current_quantity)->toBe(0.0);

    $location = inventoryAlertPosition('WH03');
    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 4.25,
    ]);
    Artisan::call('inventory:scan-alerts');

    $this->assertDatabaseHas('mes_inventory_alerts', [
        'id' => $alert->id,
        'current_quantity' => '4.250',
        'status' => InventoryAlert::PENDING,
    ]);
});

it('does not duplicate alerts and reopens closed alerts only after the stock recovers then falls again', function () {
    $material = inventoryAlertMaterial('CYCLE');
    $location = inventoryAlertPosition('WH01');
    $stock = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 5,
    ]);

    Artisan::call('inventory:scan-alerts');
    Artisan::call('inventory:scan-alerts');
    expect(InventoryAlert::query()->where('material_id', $material->id)->count())->toBe(1);

    $alert = InventoryAlert::query()->where('material_id', $material->id)->firstOrFail();
    $alert->update(['status' => InventoryAlert::CLOSED, 'resolved_at' => null]);
    Artisan::call('inventory:scan-alerts');
    expect(InventoryAlert::query()->where('material_id', $material->id)->count())->toBe(1);

    $stock->update(['current_quantity' => 20]);
    Artisan::call('inventory:scan-alerts');
    $this->assertDatabaseHas('mes_inventory_alerts', ['id' => $alert->id, 'status' => InventoryAlert::CLOSED]);
    $this->assertDatabaseHas('mes_inventory_alerts', ['id' => $alert->id, 'resolved_at' => now()]);

    $stock->update(['current_quantity' => 4]);
    Artisan::call('inventory:scan-alerts');
    expect(InventoryAlert::query()->where('material_id', $material->id)->count())->toBe(2);
});

it('releases a dismissed low-stock alert when its material is disabled', function () {
    $material = inventoryAlertMaterial('DISMISSED');
    $location = inventoryAlertPosition('WH04');
    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 5,
    ]);
    $alert = InventoryAlert::factory()->create([
        'material_id' => $material->id,
        'status' => InventoryAlert::CLOSED,
        'resolved_at' => null,
    ]);

    $material->update(['status' => false]);
    Artisan::call('inventory:scan-alerts');

    $this->assertDatabaseHas('mes_inventory_alerts', [
        'id' => $alert->id,
        'status' => InventoryAlert::CLOSED,
        'active_key' => null,
    ]);
    expect($alert->fresh()->resolved_at)->not->toBeNull();

    $material->update(['status' => true]);
    Artisan::call('inventory:scan-alerts');
    expect(InventoryAlert::query()->where('material_id', $material->id)->count())->toBe(2);
});

it('registers the inventory alert scan as a non-overlapping recurring task', function () {
    Artisan::call('schedule:list');
    $schedule = Artisan::output();

    expect($schedule)->toContain('inventory:scan-alerts')->toContain('*/5');
});
