<?php

namespace Database\Factories;

use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductSn;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProductSn>
 */
class ProductSnFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sn' => 'TEST'.fake()->unique()->numerify('########'),
            'production_order_id' => ProductionOrder::factory(),
            'production_batch_id' => fn (array $attributes) => ProductionBatch::factory()->create([
                'production_order_id' => $attributes['production_order_id'],
            ])->id,
            'material_batch_no' => null,
            'equipment_id' => null,
            'employee_id' => null,
            'status' => ProductSn::ACTIVE,
            'printed_at' => null,
            'voided_at' => null,
            'void_reason' => null,
        ];
    }
}
