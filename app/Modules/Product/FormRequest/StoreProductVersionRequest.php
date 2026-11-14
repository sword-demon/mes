<?php

namespace App\Modules\Product\FormRequest;

use App\Modules\Product\Model\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return [
            'version' => [
                'required',
                'string',
                'max:32',
                Rule::unique('mes_product_versions', 'version')->where('product_id', $product->id),
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
