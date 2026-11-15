<?php

namespace App\Modules\Quality\FormRequest;

class StoreDefectCodeRequest extends DefectCodeRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->defectRules();
    }
}
