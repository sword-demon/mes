<?php

namespace App\Modules\Production\Resource;

use App\Modules\Production\Model\ProcessRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProcessRecord
 */
class ProcessRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'production_task_id' => $this->production_task_id,
            'route_operation_id' => $this->route_operation_id,
            'sequence' => $this->sequence,
            'sn' => $this->sn,
            'employee_id' => $this->employee_id,
            'team_id' => $this->team_id,
            'equipment_id' => $this->equipment_id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'inspected_at' => $this->inspected_at,
            'submitted_at' => $this->submitted_at,
            'received_at' => $this->received_at,
            'exception_reason' => $this->exception_reason,
            'exception_at' => $this->exception_at,
            'rework_reason' => $this->rework_reason,
            'rework_at' => $this->rework_at,
            'rework_count' => $this->rework_count,
            'production_task' => $this->whenLoaded('productionTask', fn () => [
                'id' => $this->productionTask->id,
                'code' => $this->productionTask->code,
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
                    'requires_inspection' => $this->routeOperation->process->requires_inspection,
                ],
            ]),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee === null ? null : [
                'id' => $this->employee->id,
                'employee_no' => $this->employee->employee_no,
                'name' => $this->employee->name,
            ]),
            'team' => $this->whenLoaded('team', fn () => $this->team === null ? null : [
                'id' => $this->team->id,
                'code' => $this->team->code,
                'name' => $this->team->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
