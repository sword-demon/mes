<?php

namespace App\Modules\Equipment\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\System\Model\User;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EquipmentAlarmService
{
    public function __construct(
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

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
            action: 'equipment.alarm.confirm',
            actionName: '确认设备告警',
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
            action: 'equipment.alarm.process',
            actionName: '处理设备告警',
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
            action: 'equipment.alarm.close',
            actionName: '关闭设备告警',
        );
    }

    private function evaluateMetrics(EquipmentData $sample): void
    {
        $matches = [];

        foreach (EquipmentAlarm::METRICS as $metric) {
            $measured = (float) $sample->getAttribute($metric);
            $match = EquipmentAlarm::levelFor($metric, $measured);

            if ($match === null) {
                continue;
            }

            $matches[$metric] = [
                'measured' => $measured,
                'level' => $match['level'],
                'threshold' => $match['threshold'],
            ];
        }

        if ($matches === []) {
            return;
        }

        $open = $this->openAlarms(
            $sample->equipment_id,
            array_map(
                fn (string $metric): string => EquipmentAlarm::alarmCodeForMetric($metric),
                array_keys($matches),
            ),
        );

        foreach ($matches as $metric => $match) {
            $alarmCode = EquipmentAlarm::alarmCodeForMetric($metric);

            $this->record(
                $sample,
                $alarmCode,
                EquipmentAlarm::messageForMetric($metric),
                $match['level'],
                $metric,
                $match['threshold'],
                $match['measured'],
                $open->get($alarmCode),
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
        $open = $this->openAlarm($sample->equipment_id, $alarm['code']);

        $this->record(
            $sample,
            $alarm['code'],
            $alarm['message'],
            $match['level'] ?? $alarm['level'],
            $metric,
            $match['threshold'] ?? null,
            $measured,
            $open,
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
        ?EquipmentAlarm $open = null,
    ): void {
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

                $this->logAutomaticCreation($open, $message, $level);
            }

            return;
        }

        $alarm = EquipmentAlarm::query()->create([
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

        $this->logAutomaticCreation($alarm, $message, $level);
    }

    /**
     * Automatic alarm creation (and escalation to critical) is logged with
     * the audit source carried by the consuming process (system for MQTT);
     * lower-level escalations stay out of the log to keep noise down
     * (02 §9.3).
     */
    private function logAutomaticCreation(EquipmentAlarm $alarm, string $message, string $level): void
    {
        if ($level !== EquipmentAlarm::LEVEL_CRITICAL) {
            return;
        }

        $this->operationLogs->record(new OperationLogEntry(
            action: 'equipment.alarm.trigger',
            actionName: '触发设备告警',
            objectType: '设备告警',
            objectId: $alarm->id,
            objectName: $alarm->code,
            after: [
                '级别' => $alarm->levelLabel(),
                '消息' => $message,
            ],
        ));
    }

    /**
     * @param  array<int, string>  $alarmCodes
     * @return Collection<string, EquipmentAlarm>
     */
    private function openAlarms(int $equipmentId, array $alarmCodes): Collection
    {
        return EquipmentAlarm::query()
            ->where('equipment_id', $equipmentId)
            ->whereIn('alarm_code', $alarmCodes)
            ->whereIn('status', EquipmentAlarm::OPEN_STATUSES)
            ->orderByDesc('id')
            ->get()
            ->unique('alarm_code')
            ->keyBy('alarm_code');
    }

    /**
     * @param  Closure(EquipmentAlarm): array<string, mixed>  $changes
     */
    private function transition(
        EquipmentAlarm $alarm,
        string $requiredStatus,
        string $error,
        Closure $changes,
        string $action,
        string $actionName,
    ): EquipmentAlarm {
        return DB::transaction(function () use ($alarm, $requiredStatus, $error, $changes, $action, $actionName): EquipmentAlarm {
            $locked = EquipmentAlarm::query()->lockForUpdate()->findOrFail($alarm->id);
            Log::debug('alarm-transition', ['alarm' => $locked->id, 'line' => $locked->dataScopeProductionLineId(), 'user_line' => $this->dataScope->scope()->productionLineIds, 'level' => $this->dataScope->scope()->level]);
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();

            if ($locked->status !== $requiredStatus) {
                throw new ApiException($error, 422);
            }

            $locked->update($changes($locked));

            $this->operationLogs->record(new OperationLogEntry(
                action: $action,
                actionName: $actionName,
                objectType: '设备告警',
                objectId: $locked->id,
                objectName: $locked->code,
                before: ['状态' => $before],
                after: [
                    '状态' => $locked->statusLabel(),
                    ...($locked->handling_note !== null ? ['处理备注' => $locked->handling_note] : []),
                ],
            ));

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
