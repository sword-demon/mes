<?php

namespace App\Modules\Material\FormRequest;

class StoreMaterialRequest extends MaterialRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->materialRules();
    }
}
