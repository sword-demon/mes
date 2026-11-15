<?php

namespace App\Modules\Inventory\Resource;

use App\Modules\Inventory\Model\StorageLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StorageLocation
 */
class StorageLocationResource extends JsonResource
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
            'warehouse' => $this->whenLoaded(
                'warehouse',
                fn () => $this->warehouse === null
                    ? null
                    : WarehouseResource::make($this->warehouse)->resolve(),
            ),
        ];
    }
}
