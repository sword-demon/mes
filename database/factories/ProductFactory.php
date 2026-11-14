<?php

namespace Database\Factories;

use App\Modules\Product\Model\Product;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Product>
 */
class ProductFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'P'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->words(2, true),
            'model' => strtoupper(fake()->bothify('??-###')),
            'specification' => fake()->sentence(3),
            'category' => fake()->randomElement(Product::CATEGORIES),
            'unit' => '台',
            'status' => true,
        ];
    }
}
