<?php

namespace App\Modules\Organization\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionLineRequest extends FormRequest
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
            'workshop_id' => ['required', 'integer', Rule::exists('mes_workshops', 'id')->where('status', true)],
            'code' => ['required', 'string', 'max:64', Rule::unique('mes_production_lines', 'code')],
            'name' => ['required', 'string', 'max:128'],
            'capacity' => ['sometimes', 'integer', 'min:0'],
            'manager' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => '编码已存在',
            'workshop_id.exists' => '所属车间不存在或已停用',
        ];
    }
}
