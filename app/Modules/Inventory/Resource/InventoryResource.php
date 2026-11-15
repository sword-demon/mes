<?php

namespace App\Modules\Inventory\Resource;

use App\Modules\Inventory\Model\Inventory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Inventory
 */
class InventoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'location_id' => $this->location_id,
            'batch_no' => $this->batch_no,
            'current_quantity' => $this->current_quantity,
            'frozen_quantity' => $this->frozen_quantity,
            'available_quantity' => round($this->current_quantity - $this->frozen_quantity, 3),
            'material' => $this->whenLoaded('material', fn () => [
                'id' => $this->material->id,
                'code' => $this->material->code,
                'name' => $this->material->name,
                'unit' => $this->material->unit,
            ]),
            'location' => $this->whenLoaded('location', fn () => [
                'id' => $this->location->id,
                'code' => $this->location->code,
                'name' => $this->location->name,
                'warehouse' => $this->location->relationLoaded('warehouse') && $this->location->warehouse !== null
                    ? [
                        'id' => $this->location->warehouse->id,
                        'code' => $this->location->warehouse->code,
                        'name' => $this->location->warehouse->name,
                    ]
                    : null,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
