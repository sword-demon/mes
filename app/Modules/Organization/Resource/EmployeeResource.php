<?php

namespace App\Modules\Organization\Resource;

use App\Modules\Organization\Model\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
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
            'department' => $this->whenLoaded('department', fn () => $this->department === null ? null : [
                'id' => $this->department->id,
                'code' => $this->department->code,
                'name' => $this->department->name,
            ]),
            'team' => $this->whenLoaded('team', fn () => $this->team === null ? null : [
                'id' => $this->team->id,
                'code' => $this->team->code,
                'name' => $this->team->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
