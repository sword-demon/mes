<?php

namespace Database\Factories;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Equipment>
 */
class EquipmentFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'EQ'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true).'设备',
            'type' => fake()->randomElement(Equipment::TYPES),
            'production_line_id' => ProductionLine::factory(),
            'workshop_id' => fn (array $attributes) => ProductionLine::query()
                ->whereKey($attributes['production_line_id'])
                ->value('workshop_id'),
            'model' => strtoupper(fake()->bothify('MDL-###')),
            'status' => Equipment::IDLE,
            'enabled' => true,
            'owner_id' => Employee::factory(),
        ];
    }
}
