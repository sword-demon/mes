<?php

namespace App\Modules\System\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
        return [
            'username' => ['nullable', 'string', 'max:64', Rule::unique('sys_users', 'username')],
            'name' => ['required', 'string', 'max:64'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('sys_users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'department_id' => ['nullable', 'integer', Rule::exists('sys_departments', 'id')],
            'factory_id' => ['nullable', 'integer', Rule::exists('mes_factories', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('mes_employees', 'id')],
            'position' => ['nullable', 'string', 'max:64'],
            'password' => ['required', 'string', Password::default()],
            'status' => ['sometimes', 'boolean'],
            'production_line_ids' => ['array'],
            'production_line_ids.*' => ['integer', Rule::exists('mes_production_lines', 'id')],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', Rule::exists('sys_roles', 'id')],
        ];
    }
}
