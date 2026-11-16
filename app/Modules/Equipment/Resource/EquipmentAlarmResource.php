<?php

namespace App\Modules\Equipment\Resource;

use App\Modules\Equipment\Model\EquipmentAlarm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EquipmentAlarm */
class EquipmentAlarmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'equipment_id' => $this->equipment_id,
            'equipment_data_id' => $this->equipment_data_id,
            'metric' => $this->metric,
            'metric_label' => $this->metricLabel(),
            'metric_unit' => $this->metricUnit(),
            'level' => $this->level,
            'level_label' => $this->levelLabel(),
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'threshold' => $this->threshold,
            'measured_value' => $this->measured_value,
            'handling_note' => $this->handling_note,
            'confirmed_by' => $this->whenLoaded('confirmedBy', fn () => $this->confirmedBy === null ? null : [
                'id' => $this->confirmedBy->id,
                'name' => $this->confirmedBy->name,
            ]),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'handled_by' => $this->whenLoaded('handledBy', fn () => $this->handledBy === null ? null : [
                'id' => $this->handledBy->id,
                'name' => $this->handledBy->name,
            ]),
            'handled_at' => $this->handled_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'triggered_at' => $this->triggered_at->toISOString(),
            'equipment' => $this->whenLoaded('equipment', fn () => [
                'id' => $this->equipment->id,
                'code' => $this->equipment->code,
                'name' => $this->equipment->name,
                'type' => $this->equipment->type,
                'status' => $this->equipment->status,
                'status_label' => $this->equipment->statusLabel(),
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
