<?php

namespace Database\Factories;

use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\DefectiveProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefectiveProduct>
 */
class DefectiveProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_order_id' => ProductionOrder::factory(),
            'route_operation_id' => fn (array $attributes) => RouteOperation::factory()->create([
                'route_id' => ProductionOrder::query()
                    ->whereKey($attributes['production_order_id'])
                    ->firstOrFail()
                    ->route_id,
            ])->id,
            'production_batch_id' => fn (array $attributes) => ProductionBatch::factory()->create([
                'production_order_id' => $attributes['production_order_id'],
            ])->id,
            'defect_code_id' => DefectCode::factory(),
            'quantity' => 1,
            'remark' => null,
            'status' => DefectiveProduct::PENDING,
            'handled_by_id' => null,
            'scrap_reason' => null,
            'scrap_quantity' => null,
            'scrapped_at' => null,
        ];
    }
}
