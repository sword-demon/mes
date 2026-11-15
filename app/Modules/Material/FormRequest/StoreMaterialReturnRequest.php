<?php

namespace App\Modules\Material\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialReturnRequest extends FormRequest
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
            'requisition_id' => ['required', 'integer', Rule::exists('mes_material_requisitions', 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requisition_item_id' => ['required', 'integer', 'distinct', Rule::exists('mes_material_requisition_items', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => '请选择退料物料',
            'items.min' => '请选择退料物料',
            'items.*.requisition_item_id.distinct' => '同一退料单不能重复选择领料明细',
            'items.*.quantity.gt' => '退料数量必须大于 0',
            'items.*.quantity.decimal' => '退料数量最多保留 3 位小数',
        ];
    }
}
