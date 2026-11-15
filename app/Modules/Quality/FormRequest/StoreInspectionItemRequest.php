<?php

namespace App\Modules\Quality\FormRequest;

class StoreInspectionItemRequest extends InspectionItemRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->itemRules();
    }
}
