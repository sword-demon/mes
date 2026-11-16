<?php

namespace Database\Factories;

use App\Modules\System\Model\OperationLog;
use App\Modules\System\Model\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationLog>
 */
class OperationLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'user_name' => fake()->name(),
            'action' => 'production.order.transition',
            'action_name' => '订单状态流转',
            'object_type' => '生产订单',
            'object_id' => 1,
            'object_name' => 'PO-'.fake()->unique()->numerify('######'),
            'summary' => '订单状态流转：生产订单',
            'changes' => ['状态' => ['before' => '草稿', 'after' => '待审核']],
            'ip' => '127.0.0.1',
        ];
    }
}
