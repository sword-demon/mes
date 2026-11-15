<?php

namespace App\Modules\Production\Resource;

use App\Modules\Production\Model\ProductionDispatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionDispatch
 */
class ProductionDispatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'production_task_id' => $this->production_task_id,
            'route_operation_id' => $this->route_operation_id,
            'employee_id' => $this->employee_id,
            'team_id' => $this->team_id,
            'dispatched_at' => $this->dispatched_at,
            'production_task' => $this->whenLoaded('productionTask', fn () => [
                'id' => $this->productionTask->id,
                'code' => $this->productionTask->code,
                'production_order_id' => $this->productionTask->production_order_id,
                'planned_quantity' => $this->productionTask->planned_quantity,
                'produced_quantity' => $this->productionTask->produced_quantity,
                'progress_percent' => $this->productionTask->progressPercent(),
                'planned_start_at' => $this->productionTask->planned_start_at,
                'planned_end_at' => $this->productionTask->planned_end_at,
                'status' => $this->productionTask->status,
                'status_label' => $this->productionTask->statusLabel(),
            ]),
            'production_order' => $this->whenLoaded('productionTask', fn () => [
                'id' => $this->productionTask->productionOrder->id,
                'code' => $this->productionTask->productionOrder->code,
            ]),
            'route_operation' => $this->whenLoaded('routeOperation', fn () => [
                'id' => $this->routeOperation->id,
                'sequence' => $this->routeOperation->sequence,
                'process' => [
                    'id' => $this->routeOperation->process->id,
                    'code' => $this->routeOperation->process->code,
                    'name' => $this->routeOperation->process->name,
                ],
            ]),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_no' => $this->employee->employee_no,
                'name' => $this->employee->name,
            ]),
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team->id,
                'code' => $this->team->code,
                'name' => $this->team->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
