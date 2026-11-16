<?php

namespace Database\Seeders;

use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Model\InspectionTask;
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
            $this->seedInspections();
        });
    }

    /**
     * Four statuses plus both closed paths; skipped when production data is
     * absent so this seeder still runs on its own in tests.
     */
    private function seedDefects(): void
    {
        $order = ProductionOrder::query()
            ->whereIn('status', [
                ProductionOrder::IN_PROGRESS,
                ProductionOrder::PAUSED,
                ProductionOrder::COMPLETED,
                ProductionOrder::CLOSED,
            ])
            ->orderBy('code')
            ->first();
        $inspector = User::query()->where('email', 'quality1@mes.test')->first();
        $code = DefectCode::query()->where('code', 'D001')->first();

        if ($order === null || $inspector === null || $code === null) {
            return;
        }

        $batch = ProductionBatch::query()
            ->where('production_order_id', $order->id)
            ->orderBy('id')
            ->first();

        if ($batch === null) {
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

    /**
     * Fifty OQC records give the quality list and dashboard enough history;
     * the pass/fail split keeps both result states visible.
     */
    private function seedInspections(): void
    {
        // OQC records exist once an order has been released, unlike reports.
        $orders = ProductionOrder::query()
            ->whereNotIn('status', [
                ProductionOrder::DRAFT,
                ProductionOrder::PENDING,
                ProductionOrder::APPROVED,
                ProductionOrder::CANCELLED,
            ])
            ->orderBy('code')
            ->get();
        $inspectors = User::query()
            ->whereIn('email', [
                'quality1@mes.test',
                'quality2@mes.test',
                'quality3@mes.test',
                'quality4@mes.test',
            ])
            ->orderBy('email')
            ->get();

        if ($orders->isEmpty() || $inspectors->isEmpty()) {
            return;
        }

        $operations = RouteOperation::query()
            ->whereIn('route_id', $orders->pluck('route_id')->unique()->all())
            ->orderBy('sequence')
            ->get()
            ->groupBy('route_id')
            ->map(fn ($routeOperations) => $routeOperations->first());

        for ($index = 1; $index <= 50; $index++) {
            $order = $orders[($index - 1) % $orders->count()];
            $operation = $operations->get($order->route_id);

            if ($operation === null) {
                continue;
            }

            $remark = '演示 OQC 检验-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);

            InspectionTask::query()->updateOrCreate(
                [
                    'production_order_id' => $order->id,
                    'process_record_id' => null,
                    'route_operation_id' => $operation->id,
                    'inspection_type' => InspectionTask::OQC,
                    'inspection_cycle' => 0,
                    'remark' => $remark,
                ],
                [
                    'result' => $index % 4 === 0 ? InspectionTask::FAILED : InspectionTask::PASSED,
                    'inspector_id' => $inspectors[($index - 1) % $inspectors->count()]->id,
                    'inspected_at' => now()->subDays($index % 7)->subHours($index % 6),
                ],
            );
        }
    }
}
