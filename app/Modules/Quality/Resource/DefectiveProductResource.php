<?php

namespace App\Modules\Quality\Resource;

use App\Modules\Quality\Model\DefectiveProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DefectiveProduct */
class DefectiveProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $latestRework = $this->relationLoaded('reworkOrders') ? $this->reworkOrders->last() : null;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'quantity' => $this->quantity,
            'remark' => $this->remark,
            'production_order' => $this->whenLoaded('productionOrder', fn () => [
                'id' => $this->productionOrder->id,
                'code' => $this->productionOrder->code,
                'product' => [
                    'id' => $this->productionOrder->product->id,
                    'code' => $this->productionOrder->product->code,
                    'name' => $this->productionOrder->product->name,
                ],
            ]),
            'route_operation' => $this->whenLoaded('routeOperation', fn () => [
                'id' => $this->routeOperation->id,
                'sequence' => $this->routeOperation->sequence,
                'process' => [
                    'id' => $this->routeOperation->process->id,
                    'code' => $this->routeOperation->process->code,
                    'name' => $this->routeOperation->process->name,
                ],
            ]),
            'production_batch' => $this->whenLoaded('productionBatch', fn () => [
                'id' => $this->productionBatch->id,
                'code' => $this->productionBatch->code,
                'quantity' => $this->productionBatch->quantity,
            ]),
            'defect_code' => $this->whenLoaded('defectCode', fn () => [
                'id' => $this->defectCode->id,
                'code' => $this->defectCode->code,
                'name' => $this->defectCode->name,
                'category' => $this->defectCode->category,
            ]),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy === null ? null : [
                'id' => $this->handledBy->id,
                'name' => $this->handledBy->name,
            ]),
            'rework_orders' => $this->whenLoaded('reworkOrders', fn () => $this->reworkOrders->map(fn ($rework) => [
                'id' => $rework->id,
                'code' => $rework->code,
                'reason' => $rework->reason,
                'created_by' => $rework->createdBy === null ? null : [
                    'id' => $rework->createdBy->id,
                    'name' => $rework->createdBy->name,
                ],
                'recheck_result' => $rework->recheck_result,
                'recheck_remark' => $rework->recheck_remark,
                'rechecked_by' => $rework->recheckedBy === null ? null : [
                    'id' => $rework->recheckedBy->id,
                    'name' => $rework->recheckedBy->name,
                ],
                'rechecked_at' => $rework->rechecked_at,
                'created_at' => $rework->created_at,
            ])),
            'recheck_result' => $latestRework?->recheck_result,
            'recheck_remark' => $latestRework?->recheck_remark,
            'rechecked_at' => $latestRework?->rechecked_at,
            'scrap_reason' => $this->scrap_reason,
            'scrap_quantity' => $this->scrap_quantity,
            'scrapped_at' => $this->scrapped_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
