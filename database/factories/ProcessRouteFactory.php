<?php

namespace Database\Factories;

use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Product\Model\Product;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<ProcessRoute>
 */
class ProcessRouteFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'RT-'.fake()->unique()->numerify('#####'),
            'product_id' => Product::factory(),
            'version' => 'V1.0',
            'audit_status' => ProcessRoute::DRAFT,
            'audited_at' => null,
            'remark' => null,
            'status' => true,
        ];
    }
}
