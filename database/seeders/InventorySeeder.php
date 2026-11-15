<?php

namespace Database\Seeders;

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Material\Model\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    private const WAREHOUSES = [
        ['code' => 'WH01', 'name' => '原料仓'],
        ['code' => 'WH02', 'name' => '半成品仓'],
        ['code' => 'WH03', 'name' => '成品仓'],
        ['code' => 'WH04', 'name' => '包装仓'],
    ];

    private const LOCATIONS = [
        ['warehouse' => 'WH01', 'code' => 'A-01', 'name' => '原料 A-01'],
        ['warehouse' => 'WH01', 'code' => 'A-02', 'name' => '原料 A-02'],
        ['warehouse' => 'WH01', 'code' => 'B-01', 'name' => '原料 B-01'],
        ['warehouse' => 'WH01', 'code' => 'C-01', 'name' => '辅料 C-01'],
        ['warehouse' => 'WH01', 'code' => 'C-02', 'name' => '辅料 C-02'],
        ['warehouse' => 'WH02', 'code' => 'A-01', 'name' => '半成品 A-01'],
        ['warehouse' => 'WH03', 'code' => 'A-01', 'name' => '成品 A-01'],
        ['warehouse' => 'WH03', 'code' => 'A-02', 'name' => '成品 A-02'],
        ['warehouse' => 'WH04', 'code' => 'P-01', 'name' => '包装 P-01'],
        ['warehouse' => 'WH04', 'code' => 'P-02', 'name' => '包装 P-02'],
    ];

    /**
     * Opening balances per warehouse location; a re-run reconciles the balance
     * through an adjustment instead of moving stock without a ledger row.
     */
    private const OPENING_BALANCES = [
        ['code' => 'M001', 'warehouse' => 'WH01', 'location' => 'A-01', 'batch_no' => 'B20261101', 'current_quantity' => 1250, 'frozen_quantity' => 50],
        ['code' => 'M002', 'warehouse' => 'WH01', 'location' => 'A-02', 'batch_no' => 'B20261102', 'current_quantity' => 860, 'frozen_quantity' => 0],
        ['code' => 'M003', 'warehouse' => 'WH01', 'location' => 'B-01', 'batch_no' => 'B20261103', 'current_quantity' => 320, 'frozen_quantity' => 20],
        ['code' => 'M004', 'warehouse' => 'WH02', 'location' => 'A-01', 'batch_no' => 'B20261104', 'current_quantity' => 180, 'frozen_quantity' => 0],
        ['code' => 'M005', 'warehouse' => 'WH03', 'location' => 'A-01', 'batch_no' => 'B20261105', 'current_quantity' => 96, 'frozen_quantity' => 6],
        ['code' => 'M006', 'warehouse' => 'WH03', 'location' => 'A-02', 'batch_no' => 'B20261106', 'current_quantity' => 140, 'frozen_quantity' => 0],
        ['code' => 'M007', 'warehouse' => 'WH01', 'location' => 'C-01', 'batch_no' => 'B20261107', 'current_quantity' => 75, 'frozen_quantity' => 0],
        ['code' => 'M008', 'warehouse' => 'WH01', 'location' => 'C-02', 'batch_no' => 'B20261108', 'current_quantity' => 60, 'frozen_quantity' => 5],
        ['code' => 'M009', 'warehouse' => 'WH04', 'location' => 'P-01', 'batch_no' => '', 'current_quantity' => 600, 'frozen_quantity' => 0],
        ['code' => 'M010', 'warehouse' => 'WH04', 'location' => 'P-02', 'batch_no' => '', 'current_quantity' => 900, 'frozen_quantity' => 0],
    ];

    public function run(): void
    {
        $this->seedWarehouses();
        $this->seedInventory();
    }

    private function seedWarehouses(): void
    {
        foreach (self::WAREHOUSES as $definition) {
            Warehouse::query()->updateOrCreate(
                ['code' => $definition['code']],
                ['name' => $definition['name'], 'status' => true],
            );
        }

        foreach (self::LOCATIONS as $definition) {
            $warehouse = Warehouse::query()->where('code', $definition['warehouse'])->firstOrFail();

            StorageLocation::query()->updateOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => $definition['code']],
                ['name' => $definition['name'], 'status' => true],
            );
        }
    }

    private function seedInventory(): void
    {
        foreach (self::OPENING_BALANCES as $definition) {
            $material = Material::query()->where('code', $definition['code'])->firstOrFail();
            $location = StorageLocation::query()
                ->whereHas('warehouse', fn (Builder $warehouse) => $warehouse->where('code', $definition['warehouse']))
                ->where('code', $definition['location'])
                ->firstOrFail();
            $inventory = Inventory::query()->firstOrCreate(
                [
                    'material_id' => $material->id,
                    'location_id' => $location->id,
                    'batch_no' => $definition['batch_no'],
                ],
                ['current_quantity' => 0, 'frozen_quantity' => 0],
            );

            $isNew = $inventory->wasRecentlyCreated;
            $before = $this->milli($inventory->current_quantity);
            $after = $this->milli($definition['current_quantity']);

            // Frozen is a reservation state, not a quantity movement, so it has no ledger row.
            $inventory->update([
                'current_quantity' => $this->decimal($after),
                'frozen_quantity' => $this->decimal($this->milli($definition['frozen_quantity'])),
            ]);

            if (! $isNew && $before === $after) {
                continue;
            }

            InventoryTransaction::query()->create([
                'inventory_id' => $inventory->id,
                'material_id' => $material->id,
                'business_type' => $isNew ? InventoryTransaction::INBOUND : InventoryTransaction::ADJUSTMENT,
                'quantity' => $this->decimal($after - $before),
                'before_quantity' => $this->decimal($before),
                'after_quantity' => $this->decimal($after),
                'reference_no' => 'SEED-'.$definition['code'],
                'remark' => $isNew ? '期初入库' : 'Seeder 账实校准',
            ]);
        }
    }

    private function milli(mixed $value): int
    {
        return (int) round((float) $value * 1000);
    }

    private function decimal(int $milli): string
    {
        return number_format($milli / 1000, 3, '.', '');
    }
}
