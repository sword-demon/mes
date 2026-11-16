<?php

namespace App\Modules\Production\Resource;

use App\Modules\Organization\Resource\Concerns\ProjectsNamedRef;
use App\Modules\Production\Model\ProductionException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin ProductionException */
class ProductionExceptionResource extends JsonResource
{
    use ProjectsNamedRef;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'description' => $this->description,
            'handling_result' => $this->handling_result,
            'production_order' => $this->whenLoaded('productionOrder', fn () => $this->productionOrder === null ? null : [
                'id' => $this->productionOrder->id,
                'code' => $this->productionOrder->code,
                'product' => $this->namedRef($this->productionOrder->product),
            ]),
            'process_record' => $this->whenLoaded('processRecord', fn () => $this->processRecord === null ? null : [
                'id' => $this->processRecord->id,
                'sequence' => $this->processRecord->sequence,
                'sn' => $this->processRecord->sn,
                'route_operation' => [
                    'id' => $this->processRecord->routeOperation->id,
                    'sequence' => $this->processRecord->routeOperation->sequence,
                    'process' => $this->namedRef($this->processRecord->routeOperation->process),
                ],
            ]),
            'reporter' => $this->whenLoaded('reporter', fn () => $this->reporter === null ? null : [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'handler' => $this->whenLoaded('handler', fn () => $this->handler === null ? null : [
                'id' => $this->handler->id,
                'name' => $this->handler->name,
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'original_name' => $image->original_name,
                'url' => Storage::disk('public')->url($image->path),
            ])),
            'reported_at' => $this->reported_at->toISOString(),
            'handled_at' => $this->handled_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
