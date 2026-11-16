<?php

namespace Database\Factories;

use App\Modules\System\Model\LoginLog;
use App\Modules\System\Model\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginLog>
 */
class LoginLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account' => fake()->unique()->safeEmail(),
            'ip' => '127.0.0.1',
            'success' => true,
            'message' => '登录成功',
        ];
    }
}
