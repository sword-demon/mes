<?php

namespace Database\Factories;

use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<StorageLocation>
 */
class StorageLocationFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'code' => 'A-'.fake()->unique()->numerify('##'),
            'name' => '库位 '.fake()->numerify('##'),
            'status' => true,
        ];
    }
}
