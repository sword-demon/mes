<?php

namespace App\Modules\Quality\Resource;

use App\Modules\Quality\Model\InspectionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InspectionItem */
class InspectionItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'code' => $this->code, 'name' => $this->name,
            'unit' => $this->unit, 'status' => $this->status,
            'standard_value' => $this->standard->standard_value,
            'min_value' => $this->standard->min_value,
            'max_value' => $this->standard->max_value,
        ];
    }
}
