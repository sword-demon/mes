<?php

namespace Database\Seeders;

use App\Modules\Bom\Model\Bom;
use App\Modules\Bom\Model\BomItem;
use App\Modules\Material\Model\Material;
use App\Modules\Product\Model\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class ProductSeeder extends Seeder
{
    /**
     * Demo products from PRD 10.1; versions and BOMs are keyed by product so
     * re-runs update rows in place.
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
            'boms' => [
                [
                    'code' => 'BOM-P001-01',
                    'product_version' => 'V1.0',
                    'version' => 'V1.0',
                    'audit_status' => Bom::APPROVED,
                    'items' => [
                        ['material' => 'M004', 'quantity' => 1, 'loss_rate' => 0.5],
                        ['material' => 'M006', 'quantity' => 1, 'loss_rate' => 0.2],
                        ['material' => 'M009', 'quantity' => 1, 'loss_rate' => 0],
                        ['material' => 'M010', 'quantity' => 1, 'loss_rate' => 0],
                    ],
                ],
                [
                    'code' => 'BOM-P001-02',
                    'product_version' => 'V2.0',
                    'version' => 'V2.0',
                    'audit_status' => Bom::DRAFT,
                    'items' => [
                        ['material' => 'M004', 'quantity' => 1, 'loss_rate' => 0.3],
                        ['material' => 'M006', 'quantity' => 1, 'loss_rate' => 0.2],
                        ['material' => 'M009', 'quantity' => 1, 'loss_rate' => 0],
                        ['material' => 'M010', 'quantity' => 1, 'loss_rate' => 0],
                    ],
                ],
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
            'boms' => [
                [
                    'code' => 'BOM-P002-01',
                    'product_version' => 'V1.0',
                    'version' => 'V1.0',
                    'audit_status' => Bom::APPROVED,
                    'items' => [
                        ['material' => 'M002', 'quantity' => 0.35, 'loss_rate' => 2],
                        ['material' => 'M009', 'quantity' => 1, 'loss_rate' => 0],
                    ],
                ],
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
            'boms' => [
                [
                    'code' => 'BOM-P003-01',
                    'product_version' => 'V1.0',
                    'version' => 'V1.0',
                    'audit_status' => Bom::DRAFT,
                    'items' => [
                        ['material' => 'M003', 'quantity' => 1, 'loss_rate' => 1],
                        ['material' => 'M007', 'quantity' => 0.05, 'loss_rate' => 0],
                    ],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $materials = Material::query()->get(['id', 'code'])->keyBy('code');

        foreach (self::PRODUCTS as $definition) {
            $versions = $definition['versions'];
            $boms = $definition['boms'];
            unset($definition['versions'], $definition['boms']);

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

            foreach ($boms as $bomDefinition) {
                $productVersion = $product->versions()
                    ->where('version', $bomDefinition['product_version'])
                    ->firstOrFail();

                $bom = Bom::query()->firstOrNew(['code' => $bomDefinition['code']]);
                $bom->fill([
                    'product_id' => $productVersion->product_id,
                    'product_version_id' => $productVersion->id,
                    'version' => $bomDefinition['version'],
                    'audit_status' => $bomDefinition['audit_status'],
                    'status' => true,
                ]);
                $bom->audited_at = $bom->audit_status === Bom::APPROVED
                    ? ($bom->audited_at ?? Carbon::now()->subDays(7))
                    : null;
                $bom->save();

                $materialIds = [];
                foreach ($bomDefinition['items'] as $item) {
                    $materialId = $materials->get($item['material'])->id
                        ?? throw new RuntimeException("请先执行 MaterialSeeder 以提供物料 {$item['material']}");
                    $materialIds[] = $materialId;

                    BomItem::query()->updateOrCreate(
                        ['bom_id' => $bom->id, 'material_id' => $materialId],
                        ['quantity' => $item['quantity'], 'loss_rate' => $item['loss_rate']],
                    );
                }

                $bom->items()->whereNotIn('material_id', $materialIds)->delete();
            }
        }
    }
}
