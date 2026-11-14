<?php

namespace Database\Seeders;

use App\Modules\Product\Model\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Demo products from PRD 10.1; versions are keyed by product so re-runs
     * update rows in place.
     */
    private const PRODUCTS = [
        [
            'code' => 'P001',
            'name' => '智能电表',
            'model' => 'DTS-100',
            'specification' => '220V 5(60)A 单相',
            'category' => '成品',
            'unit' => '台',
            'status' => true,
            'versions' => [
                ['version' => 'V1.0', 'remark' => '首版量产'],
                ['version' => 'V2.0', 'remark' => '增加远程通信'],
            ],
        ],
        [
            'code' => 'P002',
            'name' => '电表外壳',
            'model' => 'WM-200',
            'specification' => 'ABS 阻燃',
            'category' => '部件',
            'unit' => '个',
            'status' => true,
            'versions' => [
                ['version' => 'V1.0', 'remark' => null],
            ],
        ],
        [
            'code' => 'P003',
            'name' => '采样电路板',
            'model' => 'PCB-50',
            'specification' => '双面板 1.6mm',
            'category' => '半成品',
            'unit' => '块',
            'status' => false,
            'versions' => [
                ['version' => 'V1.0', 'remark' => '暂停生产'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTS as $definition) {
            $versions = $definition['versions'];
            unset($definition['versions']);

            $product = Product::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition,
            );

            foreach ($versions as $version) {
                $product->versions()->updateOrCreate(
                    ['version' => $version['version']],
                    ['remark' => $version['remark']],
                );
            }
        }
    }
}
