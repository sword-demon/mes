<?php

namespace Database\Seeders;

use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Product\Model\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

class ProcessSeeder extends Seeder
{
    /**
     * Processes from PRD 4.5.1, keyed by code so re-runs update rows in place.
     */
    private const PROCESSES = [
        ['code' => 'OP001', 'name' => '原材料准备', 'type' => '准备', 'standard_hours' => 0.5, 'requires_inspection' => false, 'description' => '按工单领料并核对物料批次'],
        ['code' => 'OP002', 'name' => 'SMT贴片', 'type' => '加工', 'standard_hours' => 0.8, 'requires_inspection' => false, 'description' => '锡膏印刷与元件贴装'],
        ['code' => 'OP003', 'name' => '回流焊', 'type' => '加工', 'standard_hours' => 0.6, 'requires_inspection' => false, 'description' => '按温度曲线完成焊接'],
        ['code' => 'OP004', 'name' => 'AOI检测', 'type' => '检验', 'standard_hours' => 0.3, 'requires_inspection' => true, 'description' => '自动光学检测焊点缺陷'],
        ['code' => 'OP005', 'name' => '组装', 'type' => '加工', 'standard_hours' => 1.2, 'requires_inspection' => false, 'description' => '整机结构装配'],
        ['code' => 'OP006', 'name' => '功能测试', 'type' => '检验', 'standard_hours' => 0.5, 'requires_inspection' => true, 'description' => '通电校验计量与通信功能'],
        ['code' => 'OP007', 'name' => '成品检验', 'type' => '检验', 'standard_hours' => 0.4, 'requires_inspection' => true, 'description' => '外观与精度抽检'],
        ['code' => 'OP008', 'name' => '包装入库', 'type' => '包装', 'standard_hours' => 0.3, 'requires_inspection' => false, 'description' => '贴标、装箱并办理入库'],
    ];

    /**
     * Demo routes keyed by product; approved ones are usable by orders, the
     * pending one proves an unaudited route cannot be used.
     */
    private const ROUTES = [
        [
            'code' => 'RT-P001-01',
            'product' => 'P001',
            'version' => 'V1.0',
            'audit_status' => ProcessRoute::APPROVED,
            'remark' => '智能电表标准工艺路线',
            'operations' => [
                ['process' => 'OP001', 'sequence' => 1, 'standard_hours' => 0.5, 'parameters' => null],
                ['process' => 'OP002', 'sequence' => 2, 'standard_hours' => 0.8, 'parameters' => '锡膏厚度 0.12mm'],
                ['process' => 'OP003', 'sequence' => 3, 'standard_hours' => 0.6, 'parameters' => '峰值温度 245℃'],
                ['process' => 'OP004', 'sequence' => 4, 'standard_hours' => 0.3, 'parameters' => null],
                ['process' => 'OP005', 'sequence' => 5, 'standard_hours' => 1.2, 'parameters' => null],
                ['process' => 'OP006', 'sequence' => 6, 'standard_hours' => 0.5, 'parameters' => '通信距离 ≥ 100m'],
                ['process' => 'OP007', 'sequence' => 7, 'standard_hours' => 0.4, 'parameters' => null],
                ['process' => 'OP008', 'sequence' => 8, 'standard_hours' => 0.3, 'parameters' => null],
            ],
        ],
        [
            'code' => 'RT-P001-02',
            'product' => 'P001',
            'version' => 'V2.0',
            'audit_status' => ProcessRoute::PENDING,
            'remark' => '远程通信版本，待审核',
            'operations' => [
                ['process' => 'OP001', 'sequence' => 1, 'standard_hours' => 0.5, 'parameters' => null],
                ['process' => 'OP002', 'sequence' => 2, 'standard_hours' => 0.9, 'parameters' => '锡膏厚度 0.15mm'],
                ['process' => 'OP003', 'sequence' => 3, 'standard_hours' => 0.6, 'parameters' => '峰值温度 250℃'],
                ['process' => 'OP004', 'sequence' => 4, 'standard_hours' => 0.3, 'parameters' => null],
                ['process' => 'OP005', 'sequence' => 5, 'standard_hours' => 1.3, 'parameters' => null],
                ['process' => 'OP006', 'sequence' => 6, 'standard_hours' => 0.6, 'parameters' => '通信距离 ≥ 300m'],
                ['process' => 'OP007', 'sequence' => 7, 'standard_hours' => 0.4, 'parameters' => null],
                ['process' => 'OP008', 'sequence' => 8, 'standard_hours' => 0.3, 'parameters' => null],
            ],
        ],
        [
            'code' => 'RT-P002-01',
            'product' => 'P002',
            'version' => 'V1.0',
            'audit_status' => ProcessRoute::APPROVED,
            'remark' => '电表外壳工艺路线',
            'operations' => [
                ['process' => 'OP001', 'sequence' => 1, 'standard_hours' => 0.3, 'parameters' => null],
                ['process' => 'OP005', 'sequence' => 2, 'standard_hours' => 0.8, 'parameters' => null],
                ['process' => 'OP008', 'sequence' => 3, 'standard_hours' => 0.2, 'parameters' => null],
            ],
        ],
    ];

    public function run(): void
    {
        $productIds = Product::query()->pluck('id', 'code')->all();

        foreach (self::PROCESSES as $definition) {
            Process::query()->updateOrCreate(
                ['code' => $definition['code']],
                [...$definition, 'status' => true],
            );
        }

        $processIds = Process::query()->pluck('id', 'code')->all();

        foreach (self::ROUTES as $definition) {
            $productId = $productIds[$definition['product']]
                ?? throw new RuntimeException("请先执行 ProductSeeder 以提供产品 {$definition['product']}");

            $route = ProcessRoute::query()->firstOrNew(['code' => $definition['code']]);
            $route->fill([
                'product_id' => $productId,
                'version' => $definition['version'],
                'audit_status' => $definition['audit_status'],
                'remark' => $definition['remark'],
                'status' => true,
            ]);
            $route->audited_at = $route->audit_status === ProcessRoute::APPROVED
                ? ($route->audited_at ?? Carbon::now()->subDays(7))
                : null;
            $route->save();

            $sequences = [];
            foreach ($definition['operations'] as $operation) {
                $sequences[] = $operation['sequence'];

                RouteOperation::query()->updateOrCreate(
                    ['route_id' => $route->id, 'sequence' => $operation['sequence']],
                    [
                        'process_id' => $processIds[$operation['process']]
                            ?? throw new RuntimeException("请先执行 ProcessSeeder 以提供工序 {$operation['process']}"),
                        'standard_hours' => $operation['standard_hours'],
                        'parameters' => $operation['parameters'],
                    ],
                );
            }

            $route->operations()->whereNotIn('sequence', $sequences)->delete();
        }
    }
}
