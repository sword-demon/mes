<?php

namespace Database\Factories;

use App\Modules\Process\Model\Process;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Process>
 */
class ProcessFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'OP'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->word(),
            'type' => fake()->randomElement(Process::TYPES),
            'standard_hours' => 0.5,
            'requires_inspection' => false,
            'description' => null,
            'status' => true,
        ];
    }
}
