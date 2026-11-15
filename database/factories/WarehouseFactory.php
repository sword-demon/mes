<?php

namespace Database\Factories;

use App\Modules\Inventory\Model\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Warehouse>
 */
class WarehouseFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'WH'.fake()->unique()->numerify('###'),
            'name' => fake()->word().'仓',
            'status' => true,
        ];
    }
}
