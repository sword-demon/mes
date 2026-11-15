<?php

namespace App\Modules\Material\Resource;

use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialRequisitionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaterialRequisition
 */
class MaterialRequisitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'production_order_id' => $this->production_order_id,
            'production_order' => $this->whenLoaded('productionOrder', fn () => [
                'id' => $this->productionOrder->id,
                'code' => $this->productionOrder->code,
            ]),
            'status' => $this->status,
            'remark' => $this->remark,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (MaterialRequisitionItem $item) => [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'material' => $item->relationLoaded('material') ? [
                    'id' => $item->material->id,
                    'code' => $item->material->code,
                    'name' => $item->material->name,
                    'unit' => $item->material->unit,
                ] : null,
                'requested_quantity' => $item->requested_quantity,
                'issued_quantity' => $item->issued_quantity,
                'over_requirement' => (bool) ($item->over_requirement ?? false),
            ])->all()),
            'created_at' => $this->created_at,
        ];
    }
}
