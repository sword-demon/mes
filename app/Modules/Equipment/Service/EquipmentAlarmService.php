<?php

namespace App\Modules\Equipment\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\System\Model\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentAlarmService
{
    public function evaluate(EquipmentData $sample): void
    {
        if ($sample->message_type !== EquipmentData::TYPE_DATA) {
            return;
        }

        foreach (EquipmentAlarm::METRICS as $metric) {
            $match = EquipmentAlarm::levelFor($metric, (float) $sample->getAttribute($metric));

            if ($match === null) {
                continue;
            }

            $open = $this->openAlarm($sample->equipment_id, $metric);

            if ($open !== null) {
                if (EquipmentAlarm::LEVEL_RANKS[$match['level']] > EquipmentAlarm::LEVEL_RANKS[$open->level]) {
                    $open->update([
                        'level' => $match['level'],
                        'threshold' => $match['threshold'],
                        'measured_value' => (float) $sample->getAttribute($metric),
                        'equipment_data_id' => $sample->id,
                    ]);
                }

                continue;
            }

            EquipmentAlarm::query()->create([
                'code' => 'ALM-'.Str::ulid(),
                'equipment_id' => $sample->equipment_id,
                'equipment_data_id' => $sample->id,
                'metric' => $metric,
                'level' => $match['level'],
                'status' => EquipmentAlarm::PENDING,
                'threshold' => $match['threshold'],
                'measured_value' => (float) $sample->getAttribute($metric),
                'triggered_at' => $sample->recorded_at,
            ]);
        }
    }

    public function confirm(EquipmentAlarm $alarm, User $handler): EquipmentAlarm
    {
        return DB::transaction(function () use ($alarm, $handler): EquipmentAlarm {
            $locked = EquipmentAlarm::query()->lockForUpdate()->findOrFail($alarm->id);

            if ($locked->status !== EquipmentAlarm::PENDING) {
                throw new ApiException('只有待确认的设备告警可以确认', 422);
            }

            $locked->update([
                'status' => EquipmentAlarm::CONFIRMED,
                'confirmed_by_id' => $handler->id,
                'confirmed_at' => now(),
            ]);

            return $this->load($locked);
        });
    }

    public function process(EquipmentAlarm $alarm, User $handler, string $note): EquipmentAlarm
    {
        return DB::transaction(function () use ($alarm, $handler, $note): EquipmentAlarm {
            $locked = EquipmentAlarm::query()->lockForUpdate()->findOrFail($alarm->id);

            if ($locked->status !== EquipmentAlarm::CONFIRMED) {
                throw new ApiException('只有已确认的设备告警可以处理', 422);
            }

            $locked->update([
                'status' => EquipmentAlarm::PROCESSING,
                'handled_by_id' => $handler->id,
                'handled_at' => now(),
                'handling_note' => $note,
            ]);

            return $this->load($locked);
        });
    }

    public function close(EquipmentAlarm $alarm, User $handler, ?string $note = null): EquipmentAlarm
    {
        return DB::transaction(function () use ($alarm, $handler, $note): EquipmentAlarm {
            $locked = EquipmentAlarm::query()->lockForUpdate()->findOrFail($alarm->id);

            if ($locked->status !== EquipmentAlarm::PROCESSING) {
                throw new ApiException('只有处理中的设备告警可以关闭', 422);
            }

            $locked->update([
                'status' => EquipmentAlarm::CLOSED,
                'handled_by_id' => $handler->id,
                'handled_at' => now(),
                'handling_note' => $note !== null && $note !== '' ? $note : $locked->handling_note,
                'closed_at' => now(),
            ]);

            return $this->load($locked);
        });
    }

    private function openAlarm(int $equipmentId, string $metric): ?EquipmentAlarm
    {
        return EquipmentAlarm::query()
            ->where('equipment_id', $equipmentId)
            ->where('metric', $metric)
            ->whereIn('status', EquipmentAlarm::OPEN_STATUSES)
            ->latest('id')
            ->first();
    }

    private function load(EquipmentAlarm $alarm): EquipmentAlarm
    {
        return $alarm->load(EquipmentAlarm::RELATIONS);
    }
}
