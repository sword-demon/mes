<?php

namespace Database\Factories;

use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProductionTask>
 */
class ProductionTaskFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PT'.fake()->unique()->numerify('######'),
            'production_order_id' => ProductionOrder::factory()->state(['status' => ProductionOrder::RELEASED]),
            'product_id' => fn (array $attributes) => ProductionOrder::query()
                ->whereKey($attributes['production_order_id'])
                ->firstOrFail()
                ->product_id,
            'route_id' => fn (array $attributes) => ProductionOrder::query()
                ->whereKey($attributes['production_order_id'])
                ->firstOrFail()
                ->route_id,
            'production_line_id' => null,
            'team_id' => null,
            'owner_id' => null,
            'planned_quantity' => 10,
            'planned_start_at' => now()->addDay(),
            'planned_end_at' => now()->addDays(3),
            'status' => ProductionTask::PENDING,
        ];
    }
}
