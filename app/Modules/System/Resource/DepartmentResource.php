<?php

namespace App\Modules\System\Resource;

use App\Modules\System\Model\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'name' => $this->name,
            'sort' => $this->sort,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
