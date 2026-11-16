<?php

namespace Database\Factories;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentAlarm>
 */
class EquipmentAlarmFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ALM'.fake()->unique()->numerify('######'),
            'equipment_id' => Equipment::factory(),
            'metric' => 'temperature',
            'alarm_code' => 'over_temperature',
            'message' => '温度超限',
            'level' => EquipmentAlarm::LEVEL_WARNING,
            'status' => EquipmentAlarm::PENDING,
            'threshold' => 64,
            'measured_value' => 66,
            'triggered_at' => now(),
        ];
    }
}
