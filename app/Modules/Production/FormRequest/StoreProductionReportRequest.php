<?php

namespace App\Modules\Production\FormRequest;

use App\Modules\Production\Model\ProductionReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionReportRequest extends FormRequest
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
            'production_task_id' => ['required', 'integer', Rule::exists('mes_production_tasks', 'id')],
            'route_operation_id' => ['required', 'integer', Rule::exists('mes_route_operations', 'id')],
            'employee_id' => ['required', 'integer', Rule::exists('mes_employees', 'id')],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'qualified_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'defective_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'type' => ['nullable', Rule::in([ProductionReport::NORMAL, ProductionReport::BATCH])],
            'reported_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.gt' => '报工数量必须大于 0',
            'quantity.decimal' => '报工数量最多保留 3 位小数',
            'quantity.max' => '报工数量不能超过 999999999.999',
            'qualified_quantity.min' => '合格数不能小于 0',
            'defective_quantity.min' => '不良数不能小于 0',
            'type.in' => '报工类型只能是正常或批量',
        ];
    }
}
