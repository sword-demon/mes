<?php

namespace App\Modules\System\Resource;

use App\Modules\System\Model\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Menu
 */
class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'permission_id' => $this->permission_id,
            'name' => $this->name,
            'path' => $this->path,
            'icon' => $this->icon,
            'sort' => $this->sort,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
