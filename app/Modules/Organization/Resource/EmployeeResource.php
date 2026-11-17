<?php

namespace App\Modules\Organization\Resource;

use App\Core\Resources\Concerns\ProjectsNamedRef;
use App\Modules\Organization\Model\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    use ProjectsNamedRef;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_no' => $this->employee_no,
            'name' => $this->name,
            'department_id' => $this->department_id,
            'team_id' => $this->team_id,
            'position' => $this->position,
            'skill_level' => $this->skill_level,
            'phone' => $this->phone,
            'status' => $this->status,
            'department' => $this->whenLoaded('department', fn () => $this->namedRef($this->department)),
            'team' => $this->whenLoaded('team', fn () => $this->namedRef($this->team)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
