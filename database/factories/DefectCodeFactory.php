<?php

namespace Database\Factories;

use App\Modules\Quality\Model\DefectCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectCode> */
class DefectCodeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('DEF-######'), 'name' => '表面划伤', 'category' => '外观', 'status' => true];
    }
}
