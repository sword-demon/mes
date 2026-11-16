<?php

namespace App\Modules\Traceability\Resource;

use App\Modules\Material\Model\Material;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Traceability\Data\BackwardTraceBatchData;
use App\Modules\Traceability\Data\BackwardTraceData;
use App\Modules\Traceability\Data\BackwardTraceMaterialUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BackwardTraceResource extends JsonResource
{
    public function __construct(private readonly BackwardTraceData $trace)
    {
        parent::__construct($trace);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snCount = $this->trace->rows
            ->flatMap(fn (BackwardTraceBatchData $row) => $row->productSns->pluck('id'))
            ->unique()
            ->count();
        $orderCount = $this->trace->rows
            ->map(fn (BackwardTraceBatchData $row): int => $row->order->id)
            ->unique()
            ->count();
        $batchCount = $this->trace->rows
            ->map(fn (BackwardTraceBatchData $row): int => $row->batch->id)
            ->unique()
            ->count();

        return [
            'batch_no' => $this->trace->batchNo,
            'has_data' => $snCount > 0,
            'message' => $snCount > 0 ? null : '该物料批次未关联任何产品 SN',
            'data_granularity' => [
                'linkage' => 'material_requisition',
                'production_batches' => 'order',
                'product_sns' => 'sn',
            ],
            'scope_note' => '物料按生产订单发料，未细分到生产批次；同一批次号可能对应多个物料，结果按生产批次汇总并列出全部物料用量。',
            'summary' => [
                'material_count' => count($this->trace->materials),
                'production_order_count' => $orderCount,
                'production_batch_count' => $batchCount,
                'sn_count' => $snCount,
            ],
            'materials' => array_map(
                fn (Material $material): array => $this->materialRef($material),
                $this->trace->materials,
            ),
            'production_batches' => $this->trace->rows
                ->map(fn (BackwardTraceBatchData $row): array => $this->batchRow($row))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function batchRow(BackwardTraceBatchData $row): array
    {
        return [
            'id' => $row->batch->id,
            'code' => $row->batch->code,
            'quantity' => $row->batch->quantity,
            'materials' => $row->materials
                ->map(fn (BackwardTraceMaterialUsage $usage): array => [
                    'material' => $this->materialRef($usage->material),
                    'issued_quantity' => $usage->issuedQuantity,
                ])
                ->values()
                ->all(),
            'production_order' => [
                'id' => $row->order->id,
                'code' => $row->order->code,
                'status' => $row->order->status,
                'status_label' => $row->order->statusLabel(),
                'planned_quantity' => $row->order->planned_quantity,
                'produced_quantity' => $row->order->produced_quantity,
                'product' => [
                    'id' => $row->order->product->id,
                    'code' => $row->order->product->code,
                    'name' => $row->order->product->name,
                    'model' => $row->order->product->model,
                    'specification' => $row->order->product->specification,
                    'unit' => $row->order->product->unit,
                ],
            ],
            'product_sns' => $row->productSns
                ->map(fn (ProductSn $sn): array => $this->productSnRow($sn))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productSnRow(ProductSn $sn): array
    {
        return [
            'id' => $sn->id,
            'sn' => $sn->sn,
            'status' => $sn->status,
            'status_label' => $sn->statusLabel(),
            'material_batch_no' => $sn->material_batch_no,
            'printed_at' => $sn->printed_at,
            'voided_at' => $sn->voided_at,
            'void_reason' => $sn->void_reason,
            'created_at' => $sn->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function materialRef(Material $material): array
    {
        return [
            'id' => $material->id,
            'code' => $material->code,
            'name' => $material->name,
            'unit' => $material->unit,
            'batch_managed' => $material->batch_managed,
        ];
    }
}
