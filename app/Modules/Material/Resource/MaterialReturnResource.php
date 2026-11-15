<?php

namespace App\Modules\Material\Resource;

use App\Modules\Material\Model\MaterialReturn;
use App\Modules\Material\Model\MaterialReturnItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaterialReturn
 */
class MaterialReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'requisition_id' => $this->requisition_id,
            'requisition' => $this->whenLoaded('requisition', fn () => [
                'id' => $this->requisition->id,
                'code' => $this->requisition->code,
            ]),
            'production_order_id' => $this->production_order_id,
            'production_order' => $this->whenLoaded('productionOrder', fn () => [
                'id' => $this->productionOrder->id,
                'code' => $this->productionOrder->code,
            ]),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'remark' => $this->remark,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(function (MaterialReturnItem $item): array {
                $requisitionItem = $item->relationLoaded('requisitionItem') ? $item->requisitionItem : null;

                return [
                    'id' => $item->id,
                    'requisition_item_id' => $item->requisition_item_id,
                    'material_id' => $requisitionItem?->material_id,
                    'material' => $requisitionItem?->relationLoaded('material') === true ? [
                        'id' => $requisitionItem->material->id,
                        'code' => $requisitionItem->material->code,
                        'name' => $requisitionItem->material->name,
                        'unit' => $requisitionItem->material->unit,
                        'batch_managed' => $requisitionItem->material->batch_managed,
                    ] : null,
                    'quantity' => (float) $item->quantity,
                ];
            })->all()),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy === null ? null : [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ]),
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at,
        ];
    }
}
