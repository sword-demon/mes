<?php

namespace App\Modules\Production\Resource;

use App\Modules\Equipment\Resource\EquipmentResource;
use App\Modules\Organization\Resource\Concerns\ProjectsNamedRef;
use App\Modules\Production\Model\ProductionReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionReport
 */
class ProductionReportResource extends JsonResource
{
    use ProjectsNamedRef;

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
            'equipment_id' => $this->equipment_id,
            'quantity' => $this->quantity,
            'qualified_quantity' => $this->qualified_quantity,
            'defective_quantity' => $this->defective_quantity,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'reported_at' => $this->reported_at,
            'reversed_at' => $this->reversed_at,
            'production_task' => $this->whenLoaded('productionTask', fn () => [
                'id' => $this->productionTask->id,
                'code' => $this->productionTask->code,
                'production_order_id' => $this->productionTask->production_order_id,
                'planned_quantity' => $this->productionTask->planned_quantity,
                'produced_quantity' => $this->productionTask->produced_quantity,
                'progress_percent' => $this->productionTask->progressPercent(),
                'status' => $this->productionTask->status,
                'status_label' => $this->productionTask->statusLabel(),
            ]),
            'production_order' => $this->whenLoaded('productionTask', fn () => [
                'id' => $this->productionTask->productionOrder->id,
                'code' => $this->productionTask->productionOrder->code,
            ]),
            'product' => $this->whenLoaded('productionTask', fn () => $this->namedRef($this->productionTask->product)),
            'route_operation' => $this->whenLoaded('routeOperation', fn () => [
                'id' => $this->routeOperation->id,
                'sequence' => $this->routeOperation->sequence,
                'process' => $this->namedRef($this->routeOperation->process),
            ]),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_no' => $this->employee->employee_no,
                'name' => $this->employee->name,
            ]),
            'team' => $this->whenLoaded('team', fn () => $this->namedRef($this->team)),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment === null
                ? null
                : EquipmentResource::make($this->equipment)->resolve()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
