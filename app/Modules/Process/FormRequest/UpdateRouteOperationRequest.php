<?php

namespace App\Modules\Process\FormRequest;

use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;

class UpdateRouteOperationRequest extends RouteOperationRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var ProcessRoute $route */
        $route = $this->route('route');
        /** @var RouteOperation $operation */
        $operation = $this->route('operation');

        return $this->operationRules($route, $operation);
    }
}
