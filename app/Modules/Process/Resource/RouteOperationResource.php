<?php

namespace App\Modules\Process\Resource;

use App\Modules\Process\Model\RouteOperation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RouteOperation
 */
class RouteOperationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'route_id' => $this->route_id,
            'process_id' => $this->process_id,
            'sequence' => $this->sequence,
            'standard_hours' => $this->standard_hours,
            'parameters' => $this->parameters,
            'process' => $this->whenLoaded('process', fn () => [
                'id' => $this->process->id,
                'code' => $this->process->code,
                'name' => $this->process->name,
                'type' => $this->process->type,
                'requires_inspection' => $this->process->requires_inspection,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
