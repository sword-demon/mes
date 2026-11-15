<?php

namespace Database\Factories;

use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\ReworkOrder;
use App\Modules\System\Model\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReworkOrder>
 */
class ReworkOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'RW-'.fake()->unique()->numerify('######'),
            'defective_product_id' => DefectiveProduct::factory(),
            'reason' => '重新加工',
            'created_by_id' => User::factory(),
            'recheck_result' => null,
            'recheck_remark' => null,
            'rechecked_by_id' => null,
            'rechecked_at' => null,
        ];
    }
}
