<?php

namespace App\Modules\Material\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueMaterialRequisitionRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.warehouse_id' => ['required', 'integer', Rule::exists('mes_warehouses', 'id')],
            'items.*.location_id' => ['required', 'integer', Rule::exists('mes_storage_locations', 'id')],
            'items.*.batch_no' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.*.quantity.gt' => '发料数量必须大于 0',
            'items.*.quantity.decimal' => '发料数量最多保留 3 位小数',
        ];
    }
}
