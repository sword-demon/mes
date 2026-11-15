<?php

namespace App\Modules\Inventory\Resource;

use App\Modules\Inventory\Model\InventoryAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InventoryAlert */
class InventoryAlertResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'min_stock' => (float) $this->min_stock,
            'current_quantity' => (float) $this->current_quantity,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy === null ? null : [
                'id' => $this->handledBy->id,
                'name' => $this->handledBy->name,
            ]),
            'handled_at' => $this->handled_at,
            'resolved_at' => $this->resolved_at,
            'material' => $this->whenLoaded('material', fn () => [
                'id' => $this->material->id,
                'code' => $this->material->code,
                'name' => $this->material->name,
                'unit' => $this->material->unit,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
