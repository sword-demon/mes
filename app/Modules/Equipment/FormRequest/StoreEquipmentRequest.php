<?php

namespace App\Modules\Equipment\FormRequest;

use App\Modules\Equipment\Model\Equipment;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends EquipmentRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->attributeRules(),
            'status' => ['sometimes', Rule::in(Equipment::STATUSES)],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->attributeMessages();
    }
}
