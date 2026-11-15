<?php

namespace Database\Factories;

use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionTask;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProcessRecord>
 */
class ProcessRecordFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_task_id' => ProductionTask::factory(),
            'route_operation_id' => fn (array $attributes) => RouteOperation::factory()->create([
                'route_id' => ProductionTask::query()
                    ->whereKey($attributes['production_task_id'])
                    ->firstOrFail()
                    ->route_id,
            ])->id,
            'sequence' => 1,
            'sn' => null,
            'employee_id' => null,
            'team_id' => null,
            'equipment_id' => null,
            'status' => ProcessRecord::PENDING,
            'rework_count' => 0,
        ];
    }
}
