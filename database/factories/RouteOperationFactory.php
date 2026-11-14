<?php

namespace Database\Factories;

use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<RouteOperation>
 */
class RouteOperationFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id' => ProcessRoute::factory(),
            'process_id' => Process::factory(),
            'sequence' => 1,
            'standard_hours' => 0.5,
            'parameters' => null,
        ];
    }
}
