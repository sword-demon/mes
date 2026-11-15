<?php

namespace App\Modules\Production\Resource;

use App\Modules\Equipment\Resource\EquipmentResource;
use App\Modules\Organization\Resource\Concerns\ProjectsNamedRef;
use App\Modules\Production\Model\ProductSn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductSn
 */
class ProductSnResource extends JsonResource
{
    use ProjectsNamedRef;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sn' => $this->sn,
            'production_order_id' => $this->production_order_id,
            'production_batch_id' => $this->production_batch_id,
            'material_batch_no' => $this->material_batch_no,
            'equipment_id' => $this->equipment_id,
            'employee_id' => $this->employee_id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'printed_at' => $this->printed_at,
            'voided_at' => $this->voided_at,
            'void_reason' => $this->void_reason,
            'production_order' => $this->whenLoaded('productionOrder', fn () => [
                'id' => $this->productionOrder->id,
                'code' => $this->productionOrder->code,
                'planned_quantity' => $this->productionOrder->planned_quantity,
                'product' => $this->namedRef($this->productionOrder->product),
            ]),
            'production_batch' => $this->whenLoaded('productionBatch', fn () => [
                'id' => $this->productionBatch->id,
                'code' => $this->productionBatch->code,
                'quantity' => $this->productionBatch->quantity,
            ]),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee === null ? null : [
                'id' => $this->employee->id,
                'employee_no' => $this->employee->employee_no,
                'name' => $this->employee->name,
            ]),
            'equipment' => $this->whenLoaded('equipment', fn () => $this->equipment === null
                ? null
                : EquipmentResource::make($this->equipment)->resolve()),
            'process_records' => $this->whenLoaded(
                'processRecords',
                fn () => ProcessRecordResource::collection($this->processRecords)->resolve(),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
