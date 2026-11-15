<?php

namespace Database\Factories;

use App\Modules\Quality\Model\InspectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionItem> */
class InspectionItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('INS-######'), 'name' => '工作电压', 'unit' => 'V', 'status' => true];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (InspectionItem $item): void {
            $item->standard()->create(['standard_value' => 5, 'min_value' => 4.8, 'max_value' => 5.2]);
        });
    }
}
