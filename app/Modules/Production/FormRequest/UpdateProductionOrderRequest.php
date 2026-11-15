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
        $rules = $this->orderRules($order instanceof ProductionOrder ? $order : null);

        // A partial update may send only the end date; the service compares it
        // with the stored start date.
        $rules['planned_end_at'] = ['nullable', 'date'];

        return $rules;
    }
}
