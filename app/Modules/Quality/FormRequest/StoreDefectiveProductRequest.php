<?php

namespace App\Modules\Quality\FormRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDefectiveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'production_order_id' => ['required', 'integer', Rule::exists('mes_production_orders', 'id')],
            'route_operation_id' => ['required', 'integer', Rule::exists('mes_route_operations', 'id')],
            'production_batch_id' => ['required', 'integer', Rule::exists('mes_production_batches', 'id')],
            'defect_code_id' => ['required', 'integer', Rule::exists('mes_defect_codes', 'id')->where('status', true)],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['defect_code_id.exists' => '缺陷代码不存在或已停用'];
    }
}
