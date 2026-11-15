<?php

namespace Database\Factories;

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * @extends EloquentFactory<InventoryTransaction>
 */
class InventoryTransactionFactory extends EloquentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventory_id' => Inventory::factory(),
            'material_id' => fn (array $attributes) => Inventory::query()
                ->whereKey($attributes['inventory_id'])
                ->value('material_id'),
            'business_type' => InventoryTransaction::INBOUND,
            'quantity' => 10,
            'before_quantity' => 0,
            'after_quantity' => 10,
            'reference_no' => null,
            'remark' => null,
            'created_by_id' => null,
        ];
    }
}
