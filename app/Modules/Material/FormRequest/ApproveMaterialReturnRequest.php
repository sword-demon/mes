<?php

namespace App\Modules\Material\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveMaterialReturnRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => '请为退料明细指定退回库位',
            'items.*.id.distinct' => '同一退料明细不能重复指定库位',
        ];
    }
}
