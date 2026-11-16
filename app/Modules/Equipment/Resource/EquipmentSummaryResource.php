<?php

namespace App\Modules\Equipment\Resource;

use App\Modules\Equipment\Model\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Equipment
 */
class EquipmentSummaryResource extends JsonResource
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
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
        ];
    }
}
