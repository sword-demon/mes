<?php

namespace App\Modules\Equipment\Service;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class EquipmentMessageService
{
    public function __construct(
        private readonly EquipmentAlarmService $alarms,
    ) {}

    public function consume(string $topic, string $message): EquipmentData
    {
        $parts = explode('/', $topic);

        if (count($parts) !== 4 || $parts[0] !== 'mes' || $parts[1] !== config('mqtt.factory')
            || ! in_array($parts[3], EquipmentData::MESSAGE_TYPES, true)) {
            throw ValidationException::withMessages(['topic' => '设备 MQTT 主题无效']);
        }

        try {
            $payload = json_decode($message, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['payload' => '设备消息必须为有效 JSON 对象']);
        }

        if (! is_array($payload)) {
            throw ValidationException::withMessages(['payload' => '设备消息必须为有效 JSON 对象']);
        }

        $data = Validator::make($payload, [
            'device_code' => ['required', 'string', 'max:64', Rule::in([$parts[2]])],
            'timestamp' => ['required', 'date_format:Y-m-d\TH:i:s.uP,Y-m-d\TH:i:sP,Y-m-d\TH:i:s.u\Z,Y-m-d\TH:i:s\Z'],
            'status' => ['required', Rule::in([...Equipment::STATUSES, 'standby', 'maintenance'])],
            'temperature' => ['required', 'numeric', 'between:-9999999.999,9999999.999'],
            'speed' => ['required', 'integer', 'between:0,4294967295'],
            'pressure' => ['required', 'numeric', 'between:0,9999999.999'],
            'output' => ['required', 'integer', 'between:0,4294967295'],
            'runtime_seconds' => ['sometimes', 'integer', 'between:0,4294967295'],
            ...($parts[3] === EquipmentData::TYPE_ALARM ? [
                'alarm_code' => ['required', 'string', 'max:64'],
                'message' => ['required', 'string', 'max:1000'],
                'level' => ['required', Rule::in(['info', 'warning', 'critical'])],
            ] : []),
        ])->validate();
        $data['status'] = match ($data['status']) {
            'standby' => Equipment::IDLE,
            'maintenance' => Equipment::REPAIR,
            default => $data['status'],
        };
        try {
            $recordedAt = CarbonImmutable::parse($data['timestamp'])->utc();
        } catch (InvalidFormatException) {
            throw ValidationException::withMessages(['timestamp' => '设备采集时间无效']);
        }
        $latestAllowed = now()->addMinutes(5);

        if ($recordedAt->year < 1000 || $recordedAt->year > 9999) {
            throw ValidationException::withMessages(['timestamp' => '设备采集时间超出可存储范围']);
        }

        if ($recordedAt->greaterThan($latestAllowed)) {
            throw ValidationException::withMessages(['timestamp' => '设备采集时间不得超前服务器时间五分钟以上']);
        }

        return DB::transaction(function () use ($data, $parts, $recordedAt, $latestAllowed): EquipmentData {
            $equipment = Equipment::query()->where('code', $data['device_code'])->lockForUpdate()->first();

            if ($equipment === null) {
                throw ValidationException::withMessages(['device_code' => '设备不存在']);
            }

            $sample = EquipmentData::query()->firstOrCreate([
                'equipment_id' => $equipment->id,
                'message_type' => $parts[3],
                'recorded_at' => $recordedAt->format('Y-m-d H:i:s.u'),
            ], [
                'status' => $data['status'],
                'temperature' => $data['temperature'],
                'speed' => $data['speed'],
                'pressure' => $data['pressure'],
                'output' => $data['output'],
                'runtime_seconds' => $data['runtime_seconds'] ?? null,
                'alarm' => $parts[3] === EquipmentData::TYPE_ALARM ? [
                    'code' => $data['alarm_code'],
                    'message' => $data['message'],
                    'level' => $data['level'],
                ] : null,
            ]);

            if ($sample->wasRecentlyCreated && ! EquipmentData::query()
                ->where('equipment_id', $equipment->id)
                ->where('recorded_at', '>', $recordedAt->format('Y-m-d H:i:s.u'))
                ->where('recorded_at', '<=', $latestAllowed->format('Y-m-d H:i:s.u'))
                ->exists()) {
                $equipment->update(['status' => $sample->status]);
            }

            if ($sample->wasRecentlyCreated) {
                $this->alarms->evaluate($sample);
            }

            return $sample;
        });
    }
}
