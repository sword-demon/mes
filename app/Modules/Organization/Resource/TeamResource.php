<?php

namespace App\Modules\Organization\Resource;

use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Resource\Concerns\ProjectsNamedRef;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
{
    use ProjectsNamedRef;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workshop_id' => $this->workshop_id,
            'code' => $this->code,
            'name' => $this->name,
            'leader' => $this->leader,
            'status' => $this->status,
            'workshop' => $this->whenLoaded('workshop', fn () => $this->namedRef($this->workshop)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
