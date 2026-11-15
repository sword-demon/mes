<?php

namespace App\Modules\Production\FormRequest;

use App\Modules\Production\Model\ProductionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ProductionOrderRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * Store requires every field; update only validates what the request sends.
     * Business availability of the BOM and route is checked in the service.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function orderRules(?ProductionOrder $order = null): array
    {
        $presence = $order === null ? [] : ['sometimes'];
        $code = Rule::unique('mes_production_orders', 'code');

        if ($order !== null) {
            $code->ignore($order->id);
        }

        return [
            'code' => [...$presence, 'required', 'string', 'max:64', $code],
            'product_id' => [...$presence, 'required', 'integer', Rule::exists('mes_products', 'id')],
            'bom_id' => [...$presence, 'required', 'integer', Rule::exists('mes_boms', 'id')],
            'route_id' => [...$presence, 'required', 'integer', Rule::exists('mes_routes', 'id')],
            'production_line_id' => [...$presence, 'required', 'integer', Rule::exists('mes_production_lines', 'id')],
            'planned_quantity' => [...$presence, 'required', 'numeric', 'gt:0'],
            'planned_start_at' => [...$presence, 'required', 'date'],
            'planned_end_at' => ['nullable', 'date', 'after_or_equal:planned_start_at'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => '订单号已存在',
            'planned_quantity.gt' => '计划数量必须大于 0',
            'planned_end_at.after_or_equal' => '计划结束时间不能早于计划开始时间',
        ];
    }
}
