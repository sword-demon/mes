<?php

namespace App\Modules\Equipment\Service;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class EquipmentMessageService
{
    public function consume(string $topic, string $message): EquipmentData
    {
        $parts = explode('/', $topic);

        if (count($parts) !== 4 || $parts[0] !== 'mes' || $parts[1] !== config('mqtt.factory')
            || ! in_array($parts[3], ['status', 'data', 'alarm'], true)) {
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
            ...($parts[3] === 'alarm' ? [
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
        $recordedAt = CarbonImmutable::parse($data['timestamp'])->utc();

        return DB::transaction(function () use ($data, $parts, $recordedAt): EquipmentData {
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
                'alarm' => $parts[3] === 'alarm' ? [
                    'code' => $data['alarm_code'],
                    'message' => $data['message'],
                    'level' => $data['level'],
                ] : null,
            ]);

            if ($sample->wasRecentlyCreated && ! EquipmentData::query()
                ->where('equipment_id', $equipment->id)
                ->where('recorded_at', '>', $recordedAt->format('Y-m-d H:i:s.u'))
                ->exists()) {
                $equipment->update(['status' => $sample->status]);
            }

            return $sample;
        });
    }
}
