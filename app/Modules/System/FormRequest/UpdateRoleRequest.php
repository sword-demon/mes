<?php

namespace App\Modules\System\FormRequest;

use App\Modules\System\Model\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('sys_roles', 'code')->ignore($role->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', Rule::exists('sys_permissions', 'id')],
        ];
    }
}
