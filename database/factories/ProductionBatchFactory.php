<?php

namespace Database\Factories;

use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProductionBatch>
 */
class ProductionBatchFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'LOT-'.fake()->unique()->numerify('######'),
            'production_order_id' => ProductionOrder::factory(),
            'quantity' => 1,
            'remark' => null,
        ];
    }
}
