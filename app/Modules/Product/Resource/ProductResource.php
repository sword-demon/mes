<?php

namespace App\Modules\Product\Resource;

use App\Modules\Product\Model\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'model' => $this->model,
            'specification' => $this->specification,
            'category' => $this->category,
            'unit' => $this->unit,
            'status' => $this->status,
            'versions' => ProductVersionResource::collection($this->whenLoaded('versions')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
