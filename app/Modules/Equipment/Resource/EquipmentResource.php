<?php

namespace App\Modules\Equipment\Resource;

use App\Modules\Equipment\Model\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Equipment
 */
class EquipmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'model' => $this->model,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'enabled' => $this->enabled,
            'workshop_id' => $this->workshop_id,
            'workshop' => $this->whenLoaded('workshop', fn () => [
                'id' => $this->workshop->id,
                'code' => $this->workshop->code,
                'name' => $this->workshop->name,
            ]),
            'production_line_id' => $this->production_line_id,
            'production_line' => $this->whenLoaded('productionLine', fn () => [
                'id' => $this->productionLine->id,
                'code' => $this->productionLine->code,
                'name' => $this->productionLine->name,
            ]),
            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'employee_no' => $this->owner->employee_no,
                'name' => $this->owner->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
