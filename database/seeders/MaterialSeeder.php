<?php

namespace Database\Seeders;

use App\Modules\Material\Model\Material;
use Illuminate\Database\Seeder;

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

    public function run(): void
    {
        foreach (self::MATERIALS as $definition) {
            Material::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition,
            );
        }
    }
}
