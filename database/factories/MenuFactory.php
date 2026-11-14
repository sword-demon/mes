<?php

namespace Database\Factories;

use App\Modules\System\Model\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'path' => '/'.fake()->unique()->slug(2),
            'sort' => 0,
        ];
    }
}
