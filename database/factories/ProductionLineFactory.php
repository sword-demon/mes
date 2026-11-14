<?php

namespace Database\Factories;

use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProductionLine>
 */
class ProductionLineFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'code' => 'PL'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->numerify('##号产线'),
            'capacity' => fake()->numberBetween(50, 200),
            'manager' => fake()->name(),
            'status' => true,
        ];
    }
}
