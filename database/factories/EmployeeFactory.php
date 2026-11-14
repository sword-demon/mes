<?php

namespace Database\Factories;

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Employee>
 */
class EmployeeFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_no' => 'EMP'.fake()->unique()->numerify('####'),
            'name' => fake()->name(),
            'team_id' => Team::factory(),
            'position' => '操作工',
            'skill_level' => '初级',
            'phone' => fake()->numerify('139########'),
            'status' => true,
        ];
    }
}
