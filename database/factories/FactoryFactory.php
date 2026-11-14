<?php

namespace Database\Factories;

use App\Modules\Organization\Model\Factory;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Factory>
 */
class FactoryFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'FAC'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->company(),
            'address' => fake()->address(),
            'contact' => fake()->name(),
            'phone' => fake()->numerify('138########'),
            'status' => true,
        ];
    }
}
