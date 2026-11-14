<?php

namespace Database\Factories;

use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Model\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Team>
 */
class TeamFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'code' => 'TEAM'.fake()->unique()->numerify('###'),
            'name' => fake()->unique()->bothify('?班'),
            'leader' => fake()->name(),
            'status' => true,
        ];
    }
}
