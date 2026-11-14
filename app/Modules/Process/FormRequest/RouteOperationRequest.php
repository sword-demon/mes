<?php

namespace App\Modules\Process\FormRequest;

use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class RouteOperationRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * A disabled process cannot join a new route; a sequence is unique per route.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function operationRules(ProcessRoute $route, ?RouteOperation $operation = null): array
    {
        $presence = $operation === null ? [] : ['sometimes'];
        $sequence = Rule::unique('mes_route_operations', 'sequence')->where('route_id', $route->id);

        if ($operation !== null) {
            $sequence->ignore($operation->id);
        }

        return [
            'process_id' => [...$presence, 'required', 'integer', Rule::exists('mes_processes', 'id')->where('status', true)],
            'sequence' => [...$presence, 'required', 'integer', 'min:1', 'max:9999', $sequence],
            'standard_hours' => ['sometimes', 'numeric', 'min:0', 'max:9999.99', 'decimal:0,2'],
            'parameters' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'process_id.exists' => '工序不存在或已停用',
            'sequence.unique' => '工序顺序已存在',
        ];
    }
}
