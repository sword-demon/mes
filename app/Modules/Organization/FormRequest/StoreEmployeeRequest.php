<?php

namespace App\Modules\Organization\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
            'employee_no' => ['required', 'string', 'max:64', Rule::unique('mes_employees', 'employee_no')],
            'name' => ['required', 'string', 'max:64'],
            'department_id' => ['nullable', 'integer', Rule::exists('sys_departments', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('mes_teams', 'id')->where('status', true)],
            'position' => ['nullable', 'string', 'max:64'],
            'skill_level' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_no.unique' => '工号已存在',
            'team_id.exists' => '所属班组不存在或已停用',
        ];
    }
}
