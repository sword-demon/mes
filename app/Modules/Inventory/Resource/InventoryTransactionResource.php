<?php

namespace App\Modules\Inventory\Resource;

use App\Modules\Inventory\Model\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryTransaction
 */
class InventoryTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_id' => $this->inventory_id,
            'material_id' => $this->material_id,
            'business_type' => $this->business_type,
            'business_type_label' => InventoryTransaction::businessTypeLabels()[$this->business_type] ?? $this->business_type,
            'quantity' => $this->quantity,
            'before_quantity' => $this->before_quantity,
            'after_quantity' => $this->after_quantity,
            'reference_no' => $this->reference_no,
            'remark' => $this->remark,
            'material' => $this->whenLoaded('material', fn () => [
                'id' => $this->material->id,
                'code' => $this->material->code,
                'name' => $this->material->name,
                'unit' => $this->material->unit,
            ]),
            'inventory' => $this->whenLoaded('inventory', fn () => [
                'id' => $this->inventory->id,
                'location' => $this->inventory->relationLoaded('location') && $this->inventory->location !== null
                    ? [
                        'id' => $this->inventory->location->id,
                        'code' => $this->inventory->location->code,
                        'name' => $this->inventory->location->name,
                        'warehouse' => $this->inventory->location->relationLoaded('warehouse') && $this->inventory->location->warehouse !== null
                            ? [
                                'id' => $this->inventory->location->warehouse->id,
                                'code' => $this->inventory->location->warehouse->code,
                                'name' => $this->inventory->location->warehouse->name,
                            ]
                            : null,
                    ]
                    : null,
                'batch_no' => $this->inventory->batch_no,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
