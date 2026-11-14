<?php

namespace App\Modules\System\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
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
            'parent_id' => ['nullable', 'integer', Rule::exists('sys_departments', 'id')],
            'code' => ['required', 'string', 'max:64', Rule::unique('sys_departments', 'code')],
            'name' => ['required', 'string', 'max:64'],
            'sort' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
