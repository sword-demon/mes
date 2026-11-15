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
        $product = Product::factory()->create();
        $version = ProductVersion::factory()->create(['product_id' => $product->id]);
        $bom = Bom::factory()->create([
            'product_version_id' => $version->id,
            'version' => 'B1.0',
            'audit_status' => Bom::APPROVED,
        ]);
        $route = ProcessRoute::factory()->create([
            'product_id' => $product->id,
            'version' => 'R1.0',
            'audit_status' => ProcessRoute::APPROVED,
        ]);

        return [
            'code' => 'PO-'.fake()->unique()->numerify('######'),
            'product_id' => $product->id,
            'product_version_id' => $version->id,
            'bom_id' => $bom->id,
            'route_id' => $route->id,
            'production_line_id' => ProductionLine::factory(),
            'bom_version' => $bom->version,
            'route_version' => $route->version,
            'planned_quantity' => 100,
            'planned_start_at' => now()->addDay(),
            'planned_end_at' => now()->addDays(3),
            'status' => ProductionOrder::DRAFT,
            'remark' => null,
        ];
    }
}
