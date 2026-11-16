<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Service\EquipmentMessageService;

function consumeEquipmentData(array $overrides = []): void
{
    app(EquipmentMessageService::class)->consume('mes/F001/EQ-SMT-01/data', json_encode([
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T04:00:00Z',
        'status' => Equipment::RUNNING,
        'temperature' => 30,
        'speed' => 500,
        'pressure' => 0.1,
        'output' => 120,
        ...$overrides,
    ], JSON_THROW_ON_ERROR));
}

beforeEach(function () {
    $this->travelTo('2026-11-16 04:00:00');
    config(['mqtt.factory' => 'F001']);
});

it('creates a graded alert with the equipment, metric, threshold and measured value', function (
    string $metric,
    int|float $value,
    string $level,
    float $threshold,
) {
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    consumeEquipmentData([$metric => $value]);

    $alarm = EquipmentAlarm::query()->sole();

    expect($alarm->equipment_id)->toBe($equipment->id)
        ->and($alarm->equipment_data_id)->not->toBeNull()
        ->and($alarm->metric)->toBe($metric)
        ->and($alarm->alarm_code)->toBe(EquipmentAlarm::alarmCodeForMetric($metric))
        ->and($alarm->message)->toBe(EquipmentAlarm::messageForMetric($metric))
        ->and($alarm->level)->toBe($level)
        ->and($alarm->status)->toBe(EquipmentAlarm::PENDING)
        ->and($alarm->threshold)->toBe($threshold)
        ->and($alarm->measured_value)->toBe((float) $value)
        ->and($alarm->triggered_at->format('Y-m-d H:i:s.u'))->toBe('2026-11-16 04:00:00.000000');
})->with([
    'temperature info' => ['temperature', 61, EquipmentAlarm::LEVEL_INFO, 60.0],
    'temperature warning' => ['temperature', 65, EquipmentAlarm::LEVEL_WARNING, 64.0],
    'temperature critical' => ['temperature', 69, EquipmentAlarm::LEVEL_CRITICAL, 68.0],
    'speed info' => ['speed', 1230, EquipmentAlarm::LEVEL_INFO, 1200.0],
    'speed warning' => ['speed', 1380, EquipmentAlarm::LEVEL_WARNING, 1350.0],
    'speed critical' => ['speed', 1470, EquipmentAlarm::LEVEL_CRITICAL, 1450.0],
    'pressure info' => ['pressure', 0.74, EquipmentAlarm::LEVEL_INFO, 0.70],
    'pressure warning' => ['pressure', 0.82, EquipmentAlarm::LEVEL_WARNING, 0.80],
    'pressure critical' => ['pressure', 0.90, EquipmentAlarm::LEVEL_CRITICAL, 0.85],
]);

it('does not create alerts when every metric is exactly at or below its info threshold', function () {
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    consumeEquipmentData([
        'temperature' => 60,
        'speed' => 1200,
        'pressure' => 0.70,
    ]);

    $this->assertDatabaseCount('mes_equipment_alarms', 0);
});

it('creates one alert per exceeded metric for the same sample', function () {
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    consumeEquipmentData([
        'temperature' => 69,
        'speed' => 1470,
        'pressure' => 0.90,
    ]);

    expect(EquipmentAlarm::query()->count())->toBe(3)
        ->and(EquipmentAlarm::query()->distinct()->pluck('metric')->sort()->values()->all())
        ->toBe(['pressure', 'speed', 'temperature'])
        ->and(EquipmentAlarm::query()->distinct()->pluck('level')->all())
        ->toBe([EquipmentAlarm::LEVEL_CRITICAL]);
});

it('does not duplicate an open alert for the same equipment and metric until it is closed', function () {
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    consumeEquipmentData(['temperature' => 65]);
    consumeEquipmentData(['temperature' => 65]);
    consumeEquipmentData(['timestamp' => '2026-11-16T04:00:01Z', 'temperature' => 66]);

    expect(EquipmentAlarm::query()->count())->toBe(1);

    EquipmentAlarm::query()->firstOrFail()->update([
        'status' => EquipmentAlarm::CLOSED,
        'closed_at' => now(),
    ]);

    consumeEquipmentData(['timestamp' => '2026-11-16T04:00:02Z', 'temperature' => 67]);

    expect(EquipmentAlarm::query()->count())->toBe(2)
        ->and(EquipmentAlarm::query()->where('status', EquipmentAlarm::PENDING)->count())->toBe(1);
});

it('escalates an open alert when the same metric reaches a higher level without downgrading it', function () {
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    consumeEquipmentData(['temperature' => 61]);

    expect(EquipmentAlarm::query()->sole()->level)->toBe(EquipmentAlarm::LEVEL_INFO);

    consumeEquipmentData(['timestamp' => '2026-11-16T04:00:01Z', 'temperature' => 69]);

    $alarm = EquipmentAlarm::query()->sole();

    expect($alarm->level)->toBe(EquipmentAlarm::LEVEL_CRITICAL)
        ->and($alarm->threshold)->toBe(68.0)
        ->and($alarm->measured_value)->toBe(69.0);

    consumeEquipmentData(['timestamp' => '2026-11-16T04:00:02Z', 'temperature' => 65]);

    expect(EquipmentAlarm::query()->sole()->level)->toBe(EquipmentAlarm::LEVEL_CRITICAL);
});

it('creates a device fault alert from the alarm topic', function () {
    Equipment::factory()->create(['code' => 'EQ-CNC-01']);

    app(EquipmentMessageService::class)->consume('mes/F001/EQ-CNC-01/alarm', json_encode([
        'device_code' => 'EQ-CNC-01',
        'timestamp' => '2026-11-16T04:00:00Z',
        'status' => Equipment::FAULT,
        'temperature' => 30,
        'speed' => 0,
        'pressure' => 0,
        'output' => 120,
        'alarm_code' => 'device_fault',
        'message' => '设备故障',
        'level' => 'critical',
    ], JSON_THROW_ON_ERROR));

    $alarm = EquipmentAlarm::query()->sole();

    expect($alarm->alarm_code)->toBe('device_fault')
        ->and($alarm->message)->toBe('设备故障')
        ->and($alarm->level)->toBe(EquipmentAlarm::LEVEL_CRITICAL)
        ->and($alarm->metric)->toBeNull()
        ->and($alarm->threshold)->toBeNull()
        ->and($alarm->measured_value)->toBeNull()
        ->and($alarm->status)->toBe(EquipmentAlarm::PENDING);
});
