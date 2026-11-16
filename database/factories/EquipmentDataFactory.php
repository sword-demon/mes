<?php

namespace Database\Factories;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentData>
 */
class EquipmentDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'message_type' => 'data',
            'recorded_at' => now(),
            'status' => Equipment::RUNNING,
            'temperature' => fake()->randomFloat(1, 35, 65),
            'speed' => fake()->numberBetween(800, 1400),
            'pressure' => fake()->randomFloat(2, 0.4, 0.8),
            'output' => fake()->numberBetween(0, 1000),
            'alarm' => null,
        ];
    }
}
