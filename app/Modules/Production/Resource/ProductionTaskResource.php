<?php

namespace App\Modules\Production\Resource;

use App\Modules\Production\Model\ProductionTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionTask
 */
class ProductionTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'production_order_id' => $this->production_order_id,
            'product_id' => $this->product_id,
            'route_id' => $this->route_id,
            'production_line_id' => $this->production_line_id,
            'team_id' => $this->team_id,
            'owner_id' => $this->owner_id,
            'planned_quantity' => $this->planned_quantity,
            'produced_quantity' => $this->produced_quantity,
            'qualified_quantity' => $this->qualified_quantity,
            'defective_quantity' => $this->defective_quantity,
            'progress_percent' => $this->progressPercent(),
            'planned_start_at' => $this->planned_start_at,
            'planned_end_at' => $this->planned_end_at,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'production_order' => $this->whenLoaded('productionOrder', fn () => [
                'id' => $this->productionOrder->id,
                'code' => $this->productionOrder->code,
                'status' => $this->productionOrder->status,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
            'route' => $this->whenLoaded('route', fn () => [
                'id' => $this->route->id,
                'code' => $this->route->code,
                'version' => $this->route->version,
            ]),
            'production_line' => $this->whenLoaded('productionLine', fn () => $this->productionLine === null ? null : [
                'id' => $this->productionLine->id,
                'code' => $this->productionLine->code,
                'name' => $this->productionLine->name,
            ]),
            'team' => $this->whenLoaded('team', fn () => $this->team === null ? null : [
                'id' => $this->team->id,
                'code' => $this->team->code,
                'name' => $this->team->name,
            ]),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner === null ? null : [
                'id' => $this->owner->id,
                'employee_no' => $this->owner->employee_no,
                'name' => $this->owner->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
