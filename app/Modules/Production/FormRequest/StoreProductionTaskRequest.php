<?php

namespace App\Modules\Production\FormRequest;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionTaskRequest extends FormRequest
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
            'planned_quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'planned_start_at' => ['nullable', 'date'],
            'planned_end_at' => ['nullable', 'date', 'after_or_equal:planned_start_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'planned_quantity.gt' => '计划数量必须大于 0',
            'planned_quantity.decimal' => '计划数量最多保留 3 位小数',
            'planned_quantity.max' => '计划数量不能超过 999999999.999',
            'planned_end_at.after_or_equal' => '计划结束时间不能早于计划开始时间',
        ];
    }
}
