<?php

namespace Database\Seeders;

use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Model\ReworkOrder;
use App\Modules\System\Model\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QualitySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                ['VOLTAGE', '工作电压', 'V', 5, 4.8, 5.2],
                ['TEMPERATURE', '工作温度', '℃', 25, 0, 60],
                ['LENGTH', '产品长度', 'mm', 100, 99, 101],
            ] as [$code, $name, $unit, $target, $minimum, $maximum]) {
                $item = InspectionItem::query()->updateOrCreate(['code' => $code], [
                    'name' => $name, 'unit' => $unit, 'status' => true,
                ]);
                $item->standard()->updateOrCreate([], [
                    'standard_value' => $target, 'min_value' => $minimum, 'max_value' => $maximum,
                ]);
            }

            foreach ([['D001', '表面划伤', '外观'], ['D002', '电压异常', '电气'], ['D003', '长度超差', '尺寸']] as [$code, $name, $category]) {
                DefectCode::query()->updateOrCreate(['code' => $code], [
                    'name' => $name, 'category' => $category, 'status' => true,
                ]);
            }

            $this->seedDefects();
        });
    }

    /**
     * Four statuses plus both closed paths; skipped when production data is
     * absent so this seeder still runs on its own in tests.
     */
    private function seedDefects(): void
    {
        $order = ProductionOrder::query()->where('code', 'PO-DEMO-001')->first();
        $batch = ProductionBatch::query()->where('code', 'PO-DEMO-001-L01')->first();
        $inspector = User::query()->where('email', 'quality1@mes.test')->first();
        $code = DefectCode::query()->where('code', 'D001')->first();

        if ($order === null || $batch === null || $inspector === null || $code === null) {
            return;
        }

        $operation = RouteOperation::query()
            ->where('route_id', $order->route_id)
            ->orderBy('sequence')
            ->first();

        if ($operation === null) {
            return;
        }

        foreach ([
            ['待处理', DefectiveProduct::PENDING],
            ['处理中', DefectiveProduct::PROCESSING],
            ['待复检', DefectiveProduct::PENDING_RECHECK],
            ['返工关闭', DefectiveProduct::CLOSED],
            ['报废关闭', DefectiveProduct::CLOSED],
        ] as [$remark, $status]) {
            $defect = DefectiveProduct::query()->updateOrCreate(
                [
                    'production_order_id' => $order->id,
                    'route_operation_id' => $operation->id,
                    'production_batch_id' => $batch->id,
                    'defect_code_id' => $code->id,
                    'remark' => "演示不良品-{$remark}",
                ],
                [
                    'quantity' => 1,
                    'status' => $status,
                    'handled_by_id' => $status === DefectiveProduct::PENDING ? null : $inspector->id,
                    'scrap_reason' => $remark === '报废关闭' ? '演示报废' : null,
                    'scrap_quantity' => $remark === '报废关闭' ? 1 : null,
                    'scrapped_at' => $remark === '报废关闭' ? now() : null,
                ],
            );

            if ($status === DefectiveProduct::PENDING_RECHECK) {
                ReworkOrder::query()->updateOrCreate(
                    ['code' => "RW-{$defect->id}-1"],
                    [
                        'defective_product_id' => $defect->id,
                        'reason' => '演示返工',
                        'created_by_id' => $inspector->id,
                    ],
                );
            }

            if ($remark === '返工关闭') {
                ReworkOrder::query()->updateOrCreate(
                    ['code' => "RW-{$defect->id}-1"],
                    [
                        'defective_product_id' => $defect->id,
                        'reason' => '演示返工',
                        'created_by_id' => $inspector->id,
                        'recheck_result' => ReworkOrder::PASSED,
                        'recheck_remark' => '演示复检合格',
                        'rechecked_by_id' => $inspector->id,
                        'rechecked_at' => now(),
                    ],
                );
            }
        }
    }
}
