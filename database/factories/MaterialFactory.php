<?php

namespace Database\Factories;

use App\Modules\Material\Model\Material;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Material>
 */
class MaterialFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minStock = fake()->numberBetween(0, 100);

        return [
            'code' => 'M'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(Material::TYPES),
            'specification' => fake()->sentence(3),
            'unit' => 'kg',
            'min_stock' => $minStock,
            'safety_stock' => $minStock + fake()->numberBetween(10, 200),
            'batch_managed' => true,
            'status' => true,
        ];
    }
}
