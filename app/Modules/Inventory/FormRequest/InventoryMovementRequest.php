<?php

namespace App\Modules\Inventory\FormRequest;

class InventoryMovementRequest extends InventoryRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->positionRules(),
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.gt' => '出入库数量必须大于 0',
            'quantity.decimal' => '出入库数量最多保留 3 位小数',
            'quantity.max' => '出入库数量不能超过 999999999.999',
        ];
    }
}
