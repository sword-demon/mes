<?php

namespace App\Modules\Process\FormRequest;

class StoreProcessRequest extends ProcessRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->processRules();
    }
}
