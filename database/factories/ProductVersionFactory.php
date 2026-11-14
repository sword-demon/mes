<?php

namespace Database\Factories;

use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProductVersion>
 */
class ProductVersionFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'version' => 'V'.fake()->unique()->numerify('##.#'),
            'remark' => null,
            'status' => true,
        ];
    }
}
