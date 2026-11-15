<?php

namespace Database\Factories;

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Material\Model\Material;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<Inventory>
 */
class InventoryFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'location_id' => StorageLocation::factory(),
            'batch_no' => '',
            'current_quantity' => 100,
            'frozen_quantity' => 0,
        ];
    }
}
