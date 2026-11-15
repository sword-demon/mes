<?php

namespace App\Modules\Production\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductSnRequest extends FormRequest
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
            'production_order_id' => ['required', 'integer', Rule::exists('mes_production_orders', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'equipment_id' => ['nullable', 'integer', 'min:1'],
            'employee_id' => ['nullable', 'integer', Rule::exists('mes_employees', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.min' => '单次至少生成 1 个 SN',
            'quantity.max' => '单次最多生成 1000 个 SN',
        ];
    }
}
