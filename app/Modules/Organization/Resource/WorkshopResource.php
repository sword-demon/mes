<?php

namespace App\Modules\Organization\Resource;

use App\Modules\Organization\Model\Workshop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Workshop
 */
class WorkshopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'factory_id' => $this->factory_id,
            'code' => $this->code,
            'name' => $this->name,
            'manager' => $this->manager,
            'status' => $this->status,
            'factory' => $this->whenLoaded('parentFactory', fn () => [
                'id' => $this->parentFactory->id,
                'code' => $this->parentFactory->code,
                'name' => $this->parentFactory->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
