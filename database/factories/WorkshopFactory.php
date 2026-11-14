<?php

namespace Database\Factories;

use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Model\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Workshop>
 */
class WorkshopFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'factory_id' => Factory::factory(),
            'code' => 'WS'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->numerify('##号车间'),
            'manager' => fake()->name(),
            'status' => true,
        ];
    }
}
