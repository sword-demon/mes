<?php

namespace App\Modules\Product\FormRequest;

use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;

class UpdateProductVersionRequest extends ProductVersionRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Product $product */
        $product = $this->route('product');
        /** @var ProductVersion $version */
        $version = $this->route('version');

        return $this->versionRules($product, $version);
    }
}
