<?php

namespace App\Modules\Product\FormRequest;

use App\Modules\Product\Model\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ProductRequest extends FormRequest
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
    protected function productRules(?Product $product = null): array
    {
        $presence = $product === null ? [] : ['sometimes'];
        $code = Rule::unique('mes_products', 'code');

        if ($product !== null) {
            $code->ignore($product->id);
        }

        return [
            'code' => [...$presence, 'required', 'string', 'max:64', $code],
            'name' => [...$presence, 'required', 'string', 'max:128'],
            'model' => ['nullable', 'string', 'max:64'],
            'specification' => ['nullable', 'string', 'max:255'],
            'category' => [...$presence, 'required', 'string', Rule::in(Product::CATEGORIES)],
            'unit' => [...$presence, 'required', 'string', 'max:16'],
            'status' => ['sometimes', 'boolean'],
        ];
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
