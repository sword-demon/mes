<?php

namespace App\Modules\Process\FormRequest;

class StoreProcessRouteRequest extends ProcessRouteRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->routeRules();
    }
}
