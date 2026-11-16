<?php

namespace App\Modules\Equipment\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Organization\Model\ProductionLine;

class EquipmentService
{
    /**
     * @throws ApiException
     */
    public function available(
        int $equipmentId,
        ?int $productionLineId = null,
        string $unavailableMessage = '设备不存在或当前不可用',
    ): Equipment {
        $equipment = Equipment::query()->available()->whereKey($equipmentId)->first();

        if ($equipment === null) {
            throw new ApiException($unavailableMessage, 422);
        }

        if ($productionLineId !== null && $equipment->production_line_id !== $productionLineId) {
            throw new ApiException('设备不属于该生产任务的产线', 422);
        }

        return $equipment;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function create(array $data): Equipment
    {
        $this->ensureLineBelongsToWorkshop((int) $data['workshop_id'], (int) $data['production_line_id']);

        return Equipment::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function update(Equipment $equipment, array $data): Equipment
    {
        $workshopId = (int) ($data['workshop_id'] ?? $equipment->workshop_id);
        $lineId = (int) ($data['production_line_id'] ?? $equipment->production_line_id);
        $this->ensureLineBelongsToWorkshop($workshopId, $lineId);

        $equipment->update($data);

        return $equipment;
    }

    /**
     * @throws ApiException
     */
    private function ensureLineBelongsToWorkshop(int $workshopId, int $lineId): void
    {
        if (! ProductionLine::query()->whereKey($lineId)->where('workshop_id', $workshopId)->exists()) {
            throw new ApiException('所属产线不属于所选车间', 422);
        }
    }
}
