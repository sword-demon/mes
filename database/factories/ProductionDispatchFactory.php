<?php

namespace Database\Factories;

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionTask;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProductionDispatch>
 */
class ProductionDispatchFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PD'.fake()->unique()->numerify('######'),
            'production_task_id' => ProductionTask::factory(),
            'route_operation_id' => fn (array $attributes) => RouteOperation::factory()->create([
                'route_id' => ProductionTask::query()
                    ->whereKey($attributes['production_task_id'])
                    ->firstOrFail()
                    ->route_id,
            ])->id,
            'team_id' => Team::factory(),
            'employee_id' => fn (array $attributes) => Employee::factory()->create([
                'team_id' => $attributes['team_id'],
            ])->id,
            'dispatched_at' => now(),
        ];
    }
}
