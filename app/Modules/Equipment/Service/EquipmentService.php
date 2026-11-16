<?php

namespace App\Modules\Equipment\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Organization\Model\ProductionLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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
     * @return Collection<int, array{recorded_at: string, temperature: float, speed: int, pressure: float, output: int}>
     */
    public function history(Equipment $equipment, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $rangeSeconds = max(1, (int) $start->diffInSeconds($end));
        $bucketSeconds = max(1, (int) ceil($rangeSeconds / EquipmentData::HISTORY_POINT_LIMIT));
        $buckets = [];

        EquipmentData::query()
            ->where('equipment_id', $equipment->id)
            ->where('message_type', EquipmentData::TYPE_DATA)
            ->whereBetween('recorded_at', [
                $start->format('Y-m-d H:i:s.u'),
                $end->format('Y-m-d H:i:s.u'),
            ])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->select(['recorded_at', 'temperature', 'speed', 'pressure', 'output'])
            ->cursor()
            ->each(function (EquipmentData $sample) use (&$buckets, $bucketSeconds): void {
                $key = intdiv($sample->recorded_at->getTimestamp(), $bucketSeconds);
                $buckets[$key] ??= [
                    'recorded_at' => $sample->recorded_at,
                    'temperature' => 0.0,
                    'speed' => 0,
                    'pressure' => 0.0,
                    'output' => $sample->output,
                    'count' => 0,
                ];

                $buckets[$key]['recorded_at'] = $sample->recorded_at;
                $buckets[$key]['temperature'] += $sample->temperature;
                $buckets[$key]['speed'] += $sample->speed;
                $buckets[$key]['pressure'] += $sample->pressure;
                $buckets[$key]['output'] = $sample->output;
                $buckets[$key]['count']++;
            });

        return collect($buckets)
            ->map(fn (array $bucket): array => [
                'recorded_at' => $bucket['recorded_at']->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'temperature' => round($bucket['temperature'] / $bucket['count'], 3),
                'speed' => (int) round($bucket['speed'] / $bucket['count']),
                'pressure' => round($bucket['pressure'] / $bucket['count'], 3),
                'output' => $bucket['output'],
            ])
            ->values();
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
