<?php

namespace Database\Factories;

use App\Modules\System\Model\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->slug(3);

        return [
            'code' => $code,
            'name' => $code,
            'type' => fake()->randomElement(Permission::TYPES),
        ];
    }
}
