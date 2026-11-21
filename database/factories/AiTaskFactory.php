<?php

namespace Database\Factories;

use App\Modules\System\Model\AiTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiTask>
 */
class AiTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trace_id' => fake()->uuid(),
            'type' => AiTask::TYPE_EXAMPLE,
            'status' => AiTask::STATUS_PENDING,
            'input' => ['message' => fake()->sentence()],
        ];
    }
}
