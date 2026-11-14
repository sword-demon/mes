<?php

namespace Database\Factories;

use App\Modules\Bom\Model\Bom;
use App\Modules\Bom\Model\BomItem;
use App\Modules\Material\Model\Material;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<BomItem>
 */
class BomItemFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bom_id' => Bom::factory(),
            'material_id' => Material::factory(),
            'quantity' => 1,
            'loss_rate' => 0,
        ];
    }
}
