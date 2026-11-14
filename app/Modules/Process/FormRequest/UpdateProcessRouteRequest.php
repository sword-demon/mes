<?php

namespace App\Modules\Process\FormRequest;

use App\Modules\Process\Model\ProcessRoute;

class UpdateProcessRouteRequest extends ProcessRouteRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ProcessRoute $route */
        $route = $this->route('route');

        return $this->routeRules($route);
    }
}
