<?php

namespace App\Modules\Equipment\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\System\Model\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentAlarmService
{
    public function evaluate(EquipmentData $sample): void
    {
        if ($sample->message_type === EquipmentData::TYPE_DATA) {
            $this->evaluateMetrics($sample);

            return;
        }

        if ($sample->message_type === EquipmentData::TYPE_ALARM) {
            $this->evaluateAlarm($sample);
        }
    }

    public function confirm(EquipmentAlarm $alarm, User $handler): EquipmentAlarm
    {
        return $this->transition(
            $alarm,
            EquipmentAlarm::PENDING,
            '只有待确认的设备告警可以确认',
            fn (EquipmentAlarm $locked): array => [
                'status' => EquipmentAlarm::CONFIRMED,
                'confirmed_by_id' => $handler->id,
                'confirmed_at' => now(),
            ],
        );
    }

    public function process(EquipmentAlarm $alarm, User $handler, string $note): EquipmentAlarm
    {
        return $this->transition(
            $alarm,
            EquipmentAlarm::CONFIRMED,
            '只有已确认的设备告警可以处理',
            fn (EquipmentAlarm $locked): array => [
                'status' => EquipmentAlarm::PROCESSING,
                'handled_by_id' => $handler->id,
                'handled_at' => now(),
                'handling_note' => $note,
            ],
        );
    }

    public function close(EquipmentAlarm $alarm, User $handler, ?string $note = null): EquipmentAlarm
    {
        return $this->transition(
            $alarm,
            EquipmentAlarm::PROCESSING,
            '只有处理中的设备告警可以关闭',
            fn (EquipmentAlarm $locked): array => [
                'status' => EquipmentAlarm::CLOSED,
                'handled_by_id' => $handler->id,
                'handled_at' => now(),
                'handling_note' => $note !== null && $note !== '' ? $note : $locked->handling_note,
                'closed_at' => now(),
            ],
        );
    }

    private function evaluateMetrics(EquipmentData $sample): void
    {
        foreach (EquipmentAlarm::METRICS as $metric) {
            $measured = (float) $sample->getAttribute($metric);
            $match = EquipmentAlarm::levelFor($metric, $measured);

            if ($match === null) {
                continue;
            }

            $this->record(
                $sample,
                EquipmentAlarm::alarmCodeForMetric($metric),
                EquipmentAlarm::messageForMetric($metric),
                $match['level'],
                $metric,
                $match['threshold'],
                $measured,
            );
        }
    }

    private function evaluateAlarm(EquipmentData $sample): void
    {
        $alarm = $sample->alarm;

        if ($alarm === null) {
            return;
        }

        $metric = EquipmentAlarm::metricForAlarmCode($alarm['code']);
        $measured = $metric === null ? null : (float) $sample->getAttribute($metric);
        $match = $metric === null ? null : EquipmentAlarm::levelFor($metric, $measured);

        $this->record(
            $sample,
            $alarm['code'],
            $alarm['message'],
            $match['level'] ?? $alarm['level'],
            $metric,
            $match['threshold'] ?? null,
            $measured,
        );
    }

    private function record(
        EquipmentData $sample,
        string $alarmCode,
        string $message,
        string $level,
        ?string $metric,
        ?float $threshold,
        ?float $measured,
    ): void {
        $open = $this->openAlarm($sample->equipment_id, $alarmCode);

        if ($open !== null) {
            if (EquipmentAlarm::LEVEL_RANKS[$level] > EquipmentAlarm::LEVEL_RANKS[$open->level]) {
                $open->update([
                    'message' => $message,
                    'level' => $level,
                    'metric' => $metric,
                    'threshold' => $threshold,
                    'measured_value' => $measured,
                    'equipment_data_id' => $sample->id,
                ]);
            }

            return;
        }

        EquipmentAlarm::query()->create([
            'code' => 'ALM-'.Str::ulid(),
            'equipment_id' => $sample->equipment_id,
            'equipment_data_id' => $sample->id,
            'metric' => $metric,
            'alarm_code' => $alarmCode,
            'message' => $message,
            'level' => $level,
            'status' => EquipmentAlarm::PENDING,
            'threshold' => $threshold,
            'measured_value' => $measured,
            'triggered_at' => $sample->recorded_at,
        ]);
    }

    /**
     * @param  Closure(EquipmentAlarm): array<string, mixed>  $changes
     */
    private function transition(
        EquipmentAlarm $alarm,
        string $requiredStatus,
        string $error,
        Closure $changes,
    ): EquipmentAlarm {
        return DB::transaction(function () use ($alarm, $requiredStatus, $error, $changes): EquipmentAlarm {
            $locked = EquipmentAlarm::query()->lockForUpdate()->findOrFail($alarm->id);

            if ($locked->status !== $requiredStatus) {
                throw new ApiException($error, 422);
            }

            $locked->update($changes($locked));

            return $this->load($locked);
        });
    }

    private function openAlarm(int $equipmentId, string $alarmCode): ?EquipmentAlarm
    {
        return EquipmentAlarm::query()
            ->where('equipment_id', $equipmentId)
            ->where('alarm_code', $alarmCode)
            ->whereIn('status', EquipmentAlarm::OPEN_STATUSES)
            ->latest('id')
            ->first();
    }

    private function load(EquipmentAlarm $alarm): EquipmentAlarm
    {
        return $alarm->load(EquipmentAlarm::RELATIONS);
    }
}
