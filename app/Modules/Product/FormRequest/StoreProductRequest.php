<?php

namespace App\Modules\Product\FormRequest;

use App\Modules\Product\Model\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:64', Rule::unique('mes_products', 'code')],
            'name' => ['required', 'string', 'max:128'],
            'model' => ['nullable', 'string', 'max:64'],
            'specification' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(Product::CATEGORIES)],
            'unit' => ['required', 'string', 'max:16'],
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
