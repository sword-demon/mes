<?php

namespace App\Modules\Quality\FormRequest;

use App\Modules\Quality\Model\InspectionItem;

class UpdateInspectionItemRequest extends InspectionItemRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $item = $this->route('item');

        return $this->itemRules($item instanceof InspectionItem ? $item : null);
    }
}
