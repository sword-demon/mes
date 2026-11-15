<?php

namespace App\Modules\Quality\Resource;

use App\Modules\Quality\Model\InspectionTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin InspectionTask */
class InspectionTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inspection_type' => $this->inspection_type,
            'inspection_type_label' => InspectionTask::TYPE_LABELS[$this->inspection_type],
            'result' => $this->result,
            'status' => $this->status(),
            'result_label' => $this->statusLabel(),
            'inspector' => $this->whenLoaded('inspector', fn () => $this->inspector === null ? null : [
                'id' => $this->inspector->id,
                'name' => $this->inspector->name,
            ]),
            'inspected_at' => $this->inspected_at,
            'remark' => $this->remark,
            'production_order' => $this->whenLoaded('productionOrder', fn () => [
                'id' => $this->productionOrder->id,
                'code' => $this->productionOrder->code,
                'product' => [
                    'id' => $this->productionOrder->product->id,
                    'code' => $this->productionOrder->product->code,
                    'name' => $this->productionOrder->product->name,
                ],
            ]),
            'process_record' => $this->whenLoaded('processRecord', fn () => $this->processRecord === null ? null : [
                'id' => $this->processRecord->id,
                'sequence' => $this->processRecord->sequence,
                'sn' => $this->processRecord->sn,
            ]),
            'route_operation' => $this->whenLoaded('routeOperation', fn () => $this->routeOperation === null ? null : [
                'id' => $this->routeOperation->id,
                'sequence' => $this->routeOperation->sequence,
                'process' => [
                    'id' => $this->routeOperation->process->id,
                    'code' => $this->routeOperation->process->code,
                    'name' => $this->routeOperation->process->name,
                ],
            ]),
            'items' => $this->whenLoaded('results', fn () => $this->results->map(fn ($result) => [
                'inspection_item_id' => $result->inspection_item_id,
                'code' => $result->inspectionItem->code,
                'name' => $result->inspectionItem->name,
                'unit' => $result->inspectionItem->unit,
                'measured_value' => $result->measured_value,
                'standard_value' => $result->standard_value,
                'min_value' => $result->min_value,
                'max_value' => $result->max_value,
                'passed' => $result->passed,
            ])),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'original_name' => $image->original_name,
                'url' => Storage::disk('public')->url($image->path),
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
