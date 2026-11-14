<?php

namespace App\Modules\Process\FormRequest;

use App\Modules\Process\Model\ProcessRoute;

class StoreRouteOperationRequest extends RouteOperationRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ProcessRoute $route */
        $route = $this->route('route');

        return $this->operationRules($route);
    }
}
