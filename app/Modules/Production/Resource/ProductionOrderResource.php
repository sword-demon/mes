<?php

namespace App\Modules\Production\Resource;

use App\Modules\Production\Model\ProductionOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductionOrder
 */
class ProductionOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'product_id' => $this->product_id,
            'product_version_id' => $this->product_version_id,
            'bom_id' => $this->bom_id,
            'route_id' => $this->route_id,
            'production_line_id' => $this->production_line_id,
            'bom_version' => $this->bom_version,
            'route_version' => $this->route_version,
            'planned_quantity' => $this->planned_quantity,
            'produced_quantity' => $this->produced_quantity,
            'qualified_quantity' => $this->qualified_quantity,
            'defective_quantity' => $this->defective_quantity,
            'planned_start_at' => $this->planned_start_at,
            'planned_end_at' => $this->planned_end_at,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'allowed_actions' => $this->allowedActions(),
            'editable' => $this->isEditable(),
            'can_split' => $this->canSplit(),
            'can_requisition' => $this->isRequisitionable(),
            'remark' => $this->remark,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
            'product_version' => $this->whenLoaded('productVersion', fn () => [
                'id' => $this->productVersion->id,
                'version' => $this->productVersion->version,
            ]),
            'bom' => $this->whenLoaded('bom', fn () => [
                'id' => $this->bom->id,
                'code' => $this->bom->code,
                'version' => $this->bom->version,
            ]),
            'route' => $this->whenLoaded('route', fn () => [
                'id' => $this->route->id,
                'code' => $this->route->code,
                'version' => $this->route->version,
            ]),
            'production_line' => $this->whenLoaded('productionLine', fn () => [
                'id' => $this->productionLine->id,
                'code' => $this->productionLine->code,
                'name' => $this->productionLine->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
