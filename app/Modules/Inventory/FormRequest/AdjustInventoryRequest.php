<?php

namespace App\Modules\Inventory\FormRequest;

class AdjustInventoryRequest extends InventoryRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->positionRules(),
            'current_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_quantity.min' => '调整后库存不能小于 0',
            'current_quantity.decimal' => '调整后库存最多保留 3 位小数',
            'current_quantity.max' => '调整后库存不能超过 999999999.999',
        ];
    }
}
