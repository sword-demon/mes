<?php

namespace App\Modules\Equipment\Service;

use App\Core\DataScope\DataScope;
use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EquipmentService
{
    public function __construct(
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * Enabled equipment only; disabled records stay out of every dashboard total.
     *
     * @return array<string, int>
     */
    public function statusCounts(?DataScope $scope = null): array
    {
        $counts = array_fill_keys(Equipment::STATUSES, 0);

        Equipment::query()
            ->enabled()
            ->when($scope !== null && ! $scope->isAll(), fn ($query) => $query->inDataScope($scope))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->each(function (mixed $count, string $status) use (&$counts): void {
                if (array_key_exists($status, $counts)) {
                    $counts[$status] = (int) $count;
                }
            });

        return $counts;
    }

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
        $this->dataScope->ensureLine((int) $data['production_line_id']);
        $this->ensureLineBelongsToWorkshop((int) $data['workshop_id'], (int) $data['production_line_id']);

        $equipment = Equipment::query()->create($data);

        $this->operationLogs->record(new OperationLogEntry(
            action: 'equipment.ledger.create',
            actionName: '新增设备',
            objectType: '设备',
            objectId: $equipment->id,
            objectName: $equipment->code,
        ));

        return $equipment;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function update(Equipment $equipment, array $data): Equipment
    {
        $this->dataScope->ensure($equipment);
        $workshopId = (int) ($data['workshop_id'] ?? $equipment->workshop_id);
        $lineId = (int) ($data['production_line_id'] ?? $equipment->production_line_id);
        $this->ensureLineBelongsToWorkshop($workshopId, $lineId);

        if ($lineId !== (int) $equipment->production_line_id) {
            $this->dataScope->ensureLine($lineId);
        }

        $before = ['产线' => $equipment->productionLine?->name, '状态' => $equipment->statusLabel()];
        $equipment->update($data);
        $equipment->load('productionLine');

        $this->operationLogs->record(new OperationLogEntry(
            action: 'equipment.ledger.update',
            actionName: '编辑设备',
            objectType: '设备',
            objectId: $equipment->id,
            objectName: $equipment->code,
            before: $before,
            after: ['产线' => $equipment->productionLine?->name, '状态' => $equipment->statusLabel()],
        ));

        return $equipment;
    }

    /**
     * @return Collection<int, array{recorded_at: string, temperature: float, speed: int, pressure: float, output: int}>
     */
    public function history(Equipment $equipment, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $rangeSeconds = max(0, (int) $start->diffInSeconds($end));
        $bucketSeconds = max(1, (int) ceil(($rangeSeconds + 1) / EquipmentData::HISTORY_POINT_LIMIT));
        $bucketExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%s', recorded_at) AS INTEGER) / ?",
            'pgsql' => 'FLOOR(EXTRACT(EPOCH FROM recorded_at) / ?)',
            default => "FLOOR(TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', recorded_at) / ?)",
        };

        $rows = DB::select(
            <<<SQL
                WITH bucketed AS (
                    SELECT
                        {$bucketExpression} AS bucket_key,
                        id,
                        recorded_at,
                        temperature,
                        speed,
                        pressure,
                        output
                    FROM mes_equipment_data
                    WHERE equipment_id = ?
                        AND message_type = ?
                        AND recorded_at BETWEEN ? AND ?
                ),
                ranked AS (
                    SELECT
                        bucket_key,
                        recorded_at,
                        output,
                        AVG(temperature) OVER (PARTITION BY bucket_key) AS average_temperature,
                        AVG(speed) OVER (PARTITION BY bucket_key) AS average_speed,
                        AVG(pressure) OVER (PARTITION BY bucket_key) AS average_pressure,
                        ROW_NUMBER() OVER (
                            PARTITION BY bucket_key
                            ORDER BY recorded_at DESC, id DESC
                        ) AS bucket_rank
                    FROM bucketed
                )
                SELECT
                    bucket_key,
                    recorded_at,
                    output,
                    average_temperature,
                    average_speed,
                    average_pressure
                FROM ranked
                WHERE bucket_rank = 1
                ORDER BY bucket_key
                SQL,
            [
                $bucketSeconds,
                $equipment->id,
                EquipmentData::TYPE_DATA,
                $start->format('Y-m-d H:i:s.u'),
                $end->format('Y-m-d H:i:s.u'),
            ],
        );

        return collect($rows)
            ->map(function (object $bucket): array {
                $row = (array) $bucket;

                return [
                    'recorded_at' => CarbonImmutable::parse((string) $row['recorded_at'])->utc()->format('Y-m-d\TH:i:s.u\Z'),
                    'temperature' => round((float) $row['average_temperature'], 3),
                    'speed' => (int) round((float) $row['average_speed']),
                    'pressure' => round((float) $row['average_pressure'], 3),
                    'output' => (int) $row['output'],
                ];
            })
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
