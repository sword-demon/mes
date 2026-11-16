<?php

namespace Database\Factories;

use App\Modules\Production\Model\ProductionException;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\System\Model\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionException>
 */
class ProductionExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'EXC-'.fake()->unique()->numerify('######'),
            'type' => ProductionException::TYPE_PROCESS,
            'status' => ProductionException::REPORTED,
            'production_order_id' => ProductionOrder::factory(),
            'process_record_id' => null,
            'description' => fake()->sentence(),
            'reported_by_id' => User::factory(),
            'reported_at' => now(),
        ];
    }
}
