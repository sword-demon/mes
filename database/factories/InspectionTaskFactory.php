<?php

namespace Database\Factories;

use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Quality\Model\InspectionTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionTask> */
class InspectionTaskFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'production_order_id' => ProductionOrder::factory(),
            'process_record_id' => null,
            'route_operation_id' => null,
            'inspection_type' => InspectionTask::OQC,
            'result' => null,
            'inspector_id' => null,
            'inspected_at' => null,
            'remark' => null,
        ];
    }
}
