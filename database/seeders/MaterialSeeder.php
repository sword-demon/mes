<?php

namespace Database\Seeders;

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Material\Model\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MaterialSeeder extends Seeder
{
    /**
     * Demo materials covering every type; re-runs update rows in place.
     */
    private const MATERIALS = [
        [
            'code' => 'M001',
            'name' => '冷轧钢板',
            'type' => '原材料',
            'specification' => 'SPCC 1.0mm',
            'unit' => 'kg',
            'min_stock' => 200,
            'safety_stock' => 500,
            'batch_managed' => true,
            'status' => true,
        ],
        [
            'code' => 'M002',
            'name' => 'ABS 阻燃颗粒',
            'type' => '原材料',
            'specification' => 'V0 级',
            'unit' => 'kg',
            'min_stock' => 100,
            'safety_stock' => 300,
            'batch_managed' => true,
            'status' => true,
        ],
        [
            'code' => 'M003',
            'name' => 'PCB 裸板',
            'type' => '半成品',
            'specification' => '双面板 1.6mm',
            'unit' => '块',
            'min_stock' => 50,
            'safety_stock' => 150,
            'batch_managed' => true,
            'status' => true,
        ],
        [
            'code' => 'M004',
            'name' => '采样电路板组件',
            'type' => '半成品',
            'specification' => 'PCBA-50',
            'unit' => '块',
            'min_stock' => 20,
            'safety_stock' => 80,
            'batch_managed' => true,
            'status' => true,
        ],
        [
            'code' => 'M005',
            'name' => '智能电表',
            'type' => '成品',
            'specification' => 'DTS-100 220V',
            'unit' => '台',
            'min_stock' => 10,
            'safety_stock' => 30,
            'batch_managed' => true,
            'status' => true,
        ],
        [
            'code' => 'M006',
            'name' => '电表外壳组件',
            'type' => '成品',
            'specification' => 'WM-200',
            'unit' => '个',
            'min_stock' => 30,
            'safety_stock' => 100,
            'batch_managed' => true,
            'status' => true,
        ],
        [
            'code' => 'M007',
            'name' => '助焊剂',
            'type' => '辅助材料',
            'specification' => '500ml',
            'unit' => '瓶',
            'min_stock' => 20,
            'safety_stock' => 60,
            'batch_managed' => true,
            'status' => true,
        ],
        [
            'code' => 'M008',
            'name' => '无水乙醇',
            'type' => '辅助材料',
            'specification' => '99.7% 500ml',
            'unit' => '瓶',
            'min_stock' => 15,
            'safety_stock' => 50,
            'batch_managed' => true,
            'status' => true,
        ],
        [
            'code' => 'M009',
            'name' => '瓦楞纸箱',
            'type' => '包装材料',
            'specification' => '五层 400×300×200',
            'unit' => '个',
            'min_stock' => 100,
            'safety_stock' => 300,
            'batch_managed' => false,
            'status' => true,
        ],
        [
            'code' => 'M010',
            'name' => '防静电袋',
            'type' => '包装材料',
            'specification' => '200×300',
            'unit' => '个',
            'min_stock' => 200,
            'safety_stock' => 500,
            'batch_managed' => false,
            'status' => true,
        ],
    ];

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
    private const INVENTORY = [
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
        DB::transaction(function (): void {
            foreach (self::MATERIALS as $definition) {
                Material::query()->updateOrCreate(
                    ['code' => $definition['code']],
                    $definition,
                );
            }

            $this->seedWarehouses();
            $this->seedInventory();
        });
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
        foreach (self::INVENTORY as $definition) {
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
