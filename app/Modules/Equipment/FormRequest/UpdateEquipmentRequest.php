<?php

namespace App\Modules\Equipment\FormRequest;

use App\Modules\Equipment\Model\Equipment;
use Illuminate\Validation\Rule;

class UpdateEquipmentRequest extends EquipmentRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = array_map(
            static fn (array $rules): array => ['sometimes', ...$rules],
            $this->attributeRules($this->route('equipment')),
        );

        return [
            ...$rules,
            'status' => ['sometimes', 'required', Rule::in(Equipment::STATUSES)],
            'enabled' => ['sometimes', 'required', 'boolean'],
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
