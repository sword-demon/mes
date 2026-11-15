<?php

namespace Database\Factories;

use App\Modules\Inventory\Model\InventoryAlert;
use App\Modules\Material\Model\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAlert>
 */
class InventoryAlertFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'min_stock' => 20,
            'current_quantity' => 10,
            'status' => InventoryAlert::PENDING,
        ];
    }
}
