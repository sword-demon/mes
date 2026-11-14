<?php

namespace App\Modules\Organization\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkshopRequest extends FormRequest
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
            'factory_id' => ['required', 'integer', Rule::exists('mes_factories', 'id')->where('status', true)],
            'code' => ['required', 'string', 'max:64', Rule::unique('mes_workshops', 'code')],
            'name' => ['required', 'string', 'max:128'],
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
            'factory_id.exists' => '所属工厂不存在或已停用',
        ];
    }
}
