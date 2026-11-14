<?php

namespace App\Modules\Product\FormRequest;

use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ProductVersionRequest extends FormRequest
{
    final public function authorize(): bool
    {
        return true;
    }

    /**
     * A version number is unique inside its product, never across products.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function versionRules(Product $product, ?ProductVersion $version = null): array
    {
        $unique = Rule::unique('mes_product_versions', 'version')
            ->where('product_id', $product->id);

        if ($version !== null) {
            $unique->ignore($version->id);
        }

        return [
            'version' => [
                ...($version === null ? [] : ['sometimes']),
                'required',
                'string',
                'max:32',
                $unique,
            ],
            'remark' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'version.unique' => '版本号已存在',
        ];
    }
}
