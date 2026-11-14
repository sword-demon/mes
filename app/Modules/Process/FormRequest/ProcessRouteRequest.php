<?php

namespace App\Modules\Process\FormRequest;

use App\Modules\Process\Model\ProcessRoute;
use Closure;
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

        if ($route !== null) {
            $code->ignore($route->id);
        }

        $rules = [
            'code' => [...$presence, 'required', 'string', 'max:64', $code],
            'version' => [...$presence, 'required', 'string', 'max:32', $this->versionAvailable($route)],
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
     * A route version is unique inside its product, so an order always pins
     * exactly one route row.
     */
    private function versionAvailable(?ProcessRoute $route): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($route): void {
            $taken = ProcessRoute::query()
                ->where('product_id', $route->product_id ?? $this->input('product_id'))
                ->where('version', (string) $value)
                ->when($route !== null, fn ($query) => $query->whereKeyNot($route->id))
                ->exists();

            if ($taken) {
                $fail('版本号已存在');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => '编码已存在',
        ];
    }
}
