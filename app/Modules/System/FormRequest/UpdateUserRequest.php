<?php

namespace App\Modules\System\FormRequest;

use App\Modules\System\Model\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');

        return [
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                Rule::unique('sys_users', 'username')->ignore($user->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:64'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('sys_users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'department_id' => ['nullable', 'integer', Rule::exists('sys_departments', 'id')],
            'factory_id' => ['nullable', 'integer', Rule::exists('mes_factories', 'id')],
            'employee_id' => ['nullable', 'integer', Rule::exists('mes_employees', 'id')],
            'position' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', 'boolean'],
            'production_line_ids' => ['array'],
            'production_line_ids.*' => ['integer', Rule::exists('mes_production_lines', 'id')],
        ];
    }
}
