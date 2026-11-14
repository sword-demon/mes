<?php

namespace App\Modules\Bom\Resource;

use App\Modules\Bom\Model\BomItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BomItem
 */
class BomItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bom_id' => $this->bom_id,
            'material_id' => $this->material_id,
            'quantity' => $this->quantity,
            'loss_rate' => $this->loss_rate,
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
