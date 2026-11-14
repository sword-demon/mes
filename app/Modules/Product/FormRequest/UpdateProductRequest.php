<?php

namespace App\Modules\Product\FormRequest;

use App\Modules\Product\Model\Product;

class UpdateProductRequest extends ProductRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $this->productRules($product);
    }
}
