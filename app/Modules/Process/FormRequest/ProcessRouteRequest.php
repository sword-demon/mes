<?php

namespace App\Modules\Process\FormRequest;

use App\Modules\Process\Model\ProcessRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ProcessRouteRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * Store requires every field; update only validates what the request sends.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function routeRules(?ProcessRoute $route = null): array
    {
        $presence = $route === null ? [] : ['sometimes'];
        $code = Rule::unique('mes_routes', 'code');

        $version = Rule::unique('mes_routes', 'version')
            ->where('product_id', $route->product_id ?? $this->input('product_id'));

        if ($route !== null) {
            $code->ignore($route->id);
            $version->ignore($route->id);
        }

        $rules = [
            'code' => [...$presence, 'required', 'string', 'max:64', $code],
            'version' => [...$presence, 'required', 'string', 'max:32', $version],
            'remark' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];

        if ($route === null) {
            // A route is anchored to its product for life.
            $rules['product_id'] = ['required', 'integer', Rule::exists('mes_products', 'id')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => '编码已存在',
            'version.unique' => '版本号已存在',
        ];
    }
}
