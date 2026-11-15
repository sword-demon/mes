<?php

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Material\Model\Material;
use Database\Seeders\MaterialSeeder;

test('the material seeder seeds ten materials covering every type and is idempotent', function () {
    $this->seed(MaterialSeeder::class);

    $count = Material::query()->count();

    $this->seed(MaterialSeeder::class);

    expect($count)->toBe(10)
        ->and(Material::query()->count())->toBe($count)
        ->and(Material::query()->distinct()->pluck('type')->sort()->values()->all())
        ->toBe(['包装材料', '半成品', '原材料', '成品', '辅助材料'])
        ->and(Material::query()->where('batch_managed', false)->count())->toBeGreaterThan(0)
        ->and(Material::query()->where('min_stock', '>', 0)->count())->toBe(10)
        ->and(Material::query()->where('safety_stock', '>', 0)->count())->toBe(10);
});

test('the material seeder seeds warehouses, locations, inventory and a ledger whose net equals each balance', function () {
    $this->seed(MaterialSeeder::class);

    $inventories = Inventory::query()->with('location.warehouse')->get();

    expect($inventories)->not->toBeEmpty()
        ->and(Warehouse::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(StorageLocation::query()->count())->toBeGreaterThanOrEqual(2)
        ->and($inventories->pluck('location.warehouse_id')->unique()->count())->toBeGreaterThanOrEqual(2)
        ->and($inventories->pluck('location.code')->unique()->count())->toBeGreaterThanOrEqual(2);

    foreach ($inventories as $inventory) {
        $net = (float) InventoryTransaction::query()
            ->where('inventory_id', $inventory->id)
            ->sum('quantity');

        expect(round($net, 3))->toBe(round((float) $inventory->current_quantity, 3))
            ->and((float) $inventory->current_quantity)->toBeGreaterThanOrEqual((float) $inventory->frozen_quantity);
    }

    $count = $inventories->count();
    $transactions = InventoryTransaction::query()->count();

    $this->seed(MaterialSeeder::class);

    expect(Inventory::query()->count())->toBe($count)
        ->and(InventoryTransaction::query()->count())->toBe($transactions);
});
