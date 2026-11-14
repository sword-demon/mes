<?php

namespace App\Modules\Material\Resource;

use App\Modules\Material\Model\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Material
 */
class MaterialResource extends JsonResource
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
            'specification' => $this->specification,
            'unit' => $this->unit,
            'min_stock' => $this->min_stock,
            'safety_stock' => $this->safety_stock,
            'batch_managed' => $this->batch_managed,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
