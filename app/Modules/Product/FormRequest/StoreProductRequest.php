<?php

namespace App\Modules\Product\FormRequest;

class StoreProductRequest extends ProductRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->productRules();
    }
}
