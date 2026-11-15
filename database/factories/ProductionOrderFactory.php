<?php

namespace Database\Factories;

use App\Modules\Bom\Model\Bom;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use App\Modules\Production\Model\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProductionOrder>
 */
class ProductionOrderFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PO-'.fake()->unique()->numerify('######'),
            'product_id' => Product::factory(),
            'product_version_id' => ProductVersion::factory(),
            'bom_id' => Bom::factory(),
            'route_id' => ProcessRoute::factory(),
            'production_line_id' => ProductionLine::factory(),
            'bom_version' => 'V1.0',
            'route_version' => 'V1.0',
            'planned_quantity' => 100,
            'planned_start_at' => now()->addDay(),
            'planned_end_at' => now()->addDays(3),
            'status' => ProductionOrder::DRAFT,
            'remark' => null,
        ];
    }
}
