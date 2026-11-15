<?php

namespace App\Modules\Material\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequisitionRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', 'distinct', Rule::exists('mes_materials', 'id')],
            'items.*.requested_quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => '请选择领料物料',
            'items.min' => '请选择领料物料',
            'items.*.material_id.distinct' => '同一领料单不能重复选择物料',
            'items.*.requested_quantity.gt' => '领料数量必须大于 0',
            'items.*.requested_quantity.decimal' => '领料数量最多保留 3 位小数',
        ];
    }
}
