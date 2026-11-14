<?php

namespace Database\Factories;

use App\Modules\Bom\Model\Bom;
use App\Modules\Product\Model\ProductVersion;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Bom>
 */
class BomFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'BOM-'.fake()->unique()->numerify('#####'),
            'product_version_id' => ProductVersion::factory(),
            'version' => 'V1.0',
            'audit_status' => Bom::DRAFT,
            'audited_at' => null,
            'remark' => null,
            'status' => true,
        ];
    }
}
