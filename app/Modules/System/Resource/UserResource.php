<?php

namespace App\Modules\System\Resource;

use App\Modules\System\Model\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'department_id' => $this->department_id,
            'employee_id' => $this->employee_id,
            'position' => $this->position,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'department' => $this->whenLoaded('department'),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee === null ? null : [
                'id' => $this->employee->id,
                'employee_no' => $this->employee->employee_no,
                'name' => $this->employee->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
