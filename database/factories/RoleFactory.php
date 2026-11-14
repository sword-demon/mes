<?php

namespace Database\Factories;

use App\Modules\System\Model\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->unique()->jobTitle(),
            'description' => fake()->sentence(),
            'status' => true,
        ];
    }
}
