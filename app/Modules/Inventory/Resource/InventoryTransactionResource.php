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
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy === null ? null : [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'material' => $this->whenLoaded('material', fn () => [
                'id' => $this->material->id,
                'code' => $this->material->code,
                'name' => $this->material->name,
                'unit' => $this->material->unit,
            ]),
            'inventory' => $this->whenLoaded('inventory', fn () => [
                'id' => $this->inventory->id,
                'location' => $this->inventory->relationLoaded('location') && $this->inventory->location !== null
                    ? StorageLocationResource::make($this->inventory->location)->resolve()
                    : null,
                'batch_no' => $this->inventory->batch_no,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
