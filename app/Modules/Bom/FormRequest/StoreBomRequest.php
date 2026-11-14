<?php

namespace App\Modules\Bom\FormRequest;

class StoreBomRequest extends BomRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->bomRules();
    }
}
