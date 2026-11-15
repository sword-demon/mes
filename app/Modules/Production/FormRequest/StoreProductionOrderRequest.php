<?php

namespace App\Modules\Production\FormRequest;

class StoreProductionOrderRequest extends ProductionOrderRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->orderRules();
    }
}
