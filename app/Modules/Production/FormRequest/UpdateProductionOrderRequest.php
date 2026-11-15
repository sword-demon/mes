<?php

namespace App\Modules\Production\FormRequest;

use App\Modules\Production\Model\ProductionOrder;

class UpdateProductionOrderRequest extends ProductionOrderRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $order = $this->route('order');

        return $this->orderRules($order instanceof ProductionOrder ? $order : null);
    }
}
