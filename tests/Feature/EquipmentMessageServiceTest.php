<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Equipment\Service\EquipmentMessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('rejects a payload larger than the configured MQTT limit', function () {
    config(['mqtt.factory' => 'F001', 'mqtt.max_payload_bytes' => 1024]);
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    $exception = null;

    try {
        app(EquipmentMessageService::class)->consume('mes/F001/EQ-SMT-01/data', json_encode([
            'device_code' => 'EQ-SMT-01',
            'timestamp' => '2026-11-16T04:00:00Z',
            'status' => Equipment::RUNNING,
            'temperature' => 30,
            'speed' => 0,
            'pressure' => 0,
            'output' => 120,
            'padding' => str_repeat('x', 2048),
        ], JSON_THROW_ON_ERROR));
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->toBeInstanceOf(ValidationException::class)
        ->and($exception->errors()['payload'][0] ?? '')->toContain('1024');
    $this->assertDatabaseCount('mes_equipment_data', 0);
});

it('keeps a telemetry message within a bounded query count', function () {
    config(['mqtt.factory' => 'F001']);
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(EquipmentMessageService::class)->consume('mes/F001/EQ-SMT-01/data', json_encode([
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T04:00:00Z',
        'status' => Equipment::RUNNING,
        'temperature' => 69,
        'speed' => 1470,
        'pressure' => 0.90,
        'output' => 120,
    ], JSON_THROW_ON_ERROR));

    expect($queries)->toBeLessThanOrEqual(9);
});

it('rejects a device clock more than five minutes ahead without blocking the next valid state', function () {
    $this->travelTo('2026-11-16 04:00:00');
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01']);
    $messages = app(EquipmentMessageService::class);
    $payload = [
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2099-01-01T00:00:00Z',
        'status' => Equipment::RUNNING,
        'temperature' => 30,
        'speed' => 0,
        'pressure' => 0,
        'output' => 120,
    ];

    expect(fn () => $messages->consume('mes/F001/EQ-SMT-01/status', json_encode($payload, JSON_THROW_ON_ERROR)))
        ->toThrow(ValidationException::class);
    $messages->consume('mes/F001/EQ-SMT-01/status', json_encode([
        ...$payload, 'timestamp' => '2026-11-16T04:00:00Z', 'status' => Equipment::OFFLINE,
    ], JSON_THROW_ON_ERROR));

    $this->assertDatabaseCount('mes_equipment_data', 1);
    expect($equipment->fresh()->status)->toBe(Equipment::OFFLINE);
});

it('recovers current state even when an old consumer already saved a future sample', function () {
    $this->travelTo('2026-11-16 04:00:00');
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01', 'status' => Equipment::RUNNING]);
    EquipmentData::factory()->create(['equipment_id' => $equipment->id, 'recorded_at' => '2099-01-01 00:00:00']);

    app(EquipmentMessageService::class)->consume('mes/F001/EQ-SMT-01/status', json_encode([
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T04:00:00Z',
        'status' => Equipment::OFFLINE,
        'temperature' => 30,
        'speed' => 0,
        'pressure' => 0,
        'output' => 120,
    ], JSON_THROW_ON_ERROR));

    expect($equipment->fresh()->status)->toBe(Equipment::OFFLINE);
    $this->assertDatabaseCount('mes_equipment_data', 2);
});

it('allows a five minute clock skew but rejects one microsecond beyond it', function () {
    $this->travelTo('2026-11-16 04:00:00');
    config(['mqtt.factory' => 'F001']);
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);
    $messages = app(EquipmentMessageService::class);
    $payload = [
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T04:05:00Z',
        'status' => Equipment::RUNNING,
        'temperature' => 30,
        'speed' => 0,
        'pressure' => 0,
        'output' => 120,
    ];

    $sample = $messages->consume('mes/F001/EQ-SMT-01/data', json_encode($payload, JSON_THROW_ON_ERROR));

    expect($sample->recorded_at->format('H:i:s'))->toBe('04:05:00');
    expect(fn () => $messages->consume('mes/F001/EQ-SMT-01/data', json_encode([
        ...$payload, 'timestamp' => '2026-11-16T04:05:00.000001+00:00',
    ], JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('mes_equipment_data', 1);
});

it('treats UTC dates outside the database range as invalid messages', function (string $timestamp) {
    $this->travelTo('2026-11-16 04:00:00');
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    expect(fn () => app(EquipmentMessageService::class)->consume('mes/F001/EQ-SMT-01/data', json_encode([
        'device_code' => 'EQ-SMT-01',
        'timestamp' => $timestamp,
        'status' => Equipment::RUNNING,
        'temperature' => 30,
        'speed' => 0,
        'pressure' => 0,
        'output' => 120,
    ], JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('mes_equipment_data', 0);
    expect($equipment->fresh()->status)->toBe(Equipment::IDLE);
})->with([
    'UTC conversion exceeds year 9999' => '9999-12-31T23:59:59-14:00',
    'UTC conversion precedes year 1000' => '1000-01-01T00:00:00+14:00',
    'year zero' => '0000-01-01T00:00:00Z',
    'invalid timezone offset' => '2026-11-16T04:00:00-99:00',
]);

it('saves a telemetry sample and updates the equipment state without enabling it', function () {
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create([
        'code' => 'EQ-SMT-01',
        'status' => Equipment::OFFLINE,
        'enabled' => false,
    ]);

    $sample = app(EquipmentMessageService::class)->consume('mes/F001/EQ-SMT-01/data', json_encode([
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T12:00:00.123456+08:00',
        'status' => 'running',
        'temperature' => 65.5,
        'speed' => 1200,
        'pressure' => 0.85,
        'output' => 120,
    ], JSON_THROW_ON_ERROR));

    expect($sample->equipment_id)->toBe($equipment->id);
    expect($sample->message_type)->toBe('data');
    expect($sample->recorded_at->format('Y-m-d H:i:s.uP'))->toBe('2026-11-16 04:00:00.123456+00:00');
    expect($sample->temperature)->toBe(65.5);
    expect($sample->speed)->toBe(1200);
    expect($sample->pressure)->toBe(0.85);
    expect($sample->output)->toBe(120);
    expect($sample->fresh()->runtime_seconds)->toBeNull();
    $this->assertDatabaseHas('mes_equipment_data', [
        'equipment_id' => $equipment->id,
        'message_type' => 'data',
        'status' => Equipment::RUNNING,
        'output' => 120,
    ]);
    expect($equipment->fresh()->status)->toBe(Equipment::RUNNING);
    expect($equipment->fresh()->enabled)->toBeFalse();
});

it('preserves cumulative runtime in seconds for each MQTT message type', function (string $kind) {
    config(['mqtt.factory' => 'F001']);
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    $sample = app(EquipmentMessageService::class)->consume("mes/F001/EQ-SMT-01/{$kind}", json_encode([
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T04:00:00Z',
        'status' => Equipment::RUNNING,
        'temperature' => 60,
        'speed' => 1200,
        'pressure' => 0.7,
        'output' => 120,
        'runtime_seconds' => 3600,
        'alarm_code' => 'over_speed',
        'message' => '转速超限',
        'level' => 'warning',
    ], JSON_THROW_ON_ERROR));

    expect($sample->fresh()->runtime_seconds)->toBe(3600);
})->with(['status', 'data', 'alarm']);

it('normalizes simulator states when consuming status messages', function (string $incoming, string $expected) {
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    $sample = app(EquipmentMessageService::class)->consume('mes/F001/EQ-SMT-01/status', json_encode([
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T04:00:00.123456+00:00',
        'status' => $incoming,
        'temperature' => 30,
        'speed' => 0,
        'pressure' => 0,
        'output' => 120,
    ], JSON_THROW_ON_ERROR));

    expect($sample->status)->toBe($expected);
    expect($sample->message_type)->toBe('status');
    expect($equipment->fresh()->status)->toBe($expected);
})->with([
    'standby becomes idle' => ['standby', Equipment::IDLE],
    'maintenance becomes repair' => ['maintenance', Equipment::REPAIR],
    'offline remains offline' => ['offline', Equipment::OFFLINE],
]);

it('deduplicates redelivered messages and keeps newer equipment state when history arrives late', function () {
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01']);
    $messages = app(EquipmentMessageService::class);
    $payload = [
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T04:00:02.123456+00:00',
        'status' => Equipment::RUNNING,
        'temperature' => 60,
        'speed' => 1000,
        'pressure' => 0.7,
        'output' => 120,
    ];

    $first = $messages->consume('mes/F001/EQ-SMT-01/data', json_encode($payload, JSON_THROW_ON_ERROR));
    $duplicate = $messages->consume('mes/F001/EQ-SMT-01/data', json_encode($payload, JSON_THROW_ON_ERROR));
    $older = $messages->consume('mes/F001/EQ-SMT-01/status', json_encode([
        ...$payload,
        'timestamp' => '2026-11-16T04:00:01.123456+00:00',
        'status' => Equipment::OFFLINE,
    ], JSON_THROW_ON_ERROR));

    expect($duplicate->id)->toBe($first->id);
    expect($older->status)->toBe(Equipment::OFFLINE);
    $this->assertDatabaseCount('mes_equipment_data', 2);
    expect($equipment->fresh()->status)->toBe(Equipment::RUNNING);
    expect(EquipmentData::query()->latest('recorded_at')->first()->id)->toBe($first->id);
});

it('preserves alarm message details with the sampled equipment values', function () {
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-CNC-01']);

    $sample = app(EquipmentMessageService::class)->consume('mes/F001/EQ-CNC-01/alarm', json_encode([
        'device_code' => 'EQ-CNC-01',
        'timestamp' => '2026-11-16T04:00:00.123456+00:00',
        'status' => Equipment::FAULT,
        'temperature' => 30,
        'speed' => 0,
        'pressure' => 0,
        'output' => 120,
        'alarm_code' => 'device_fault',
        'message' => '设备故障',
        'level' => 'critical',
        'enabled' => false,
    ], JSON_THROW_ON_ERROR));

    expect($sample->fresh()->alarm)->toBe([
        'code' => 'device_fault',
        'message' => '设备故障',
        'level' => 'critical',
    ]);
    expect($sample->message_type)->toBe('alarm');
    expect($sample->output)->toBe(120);
    expect($equipment->fresh()->status)->toBe(Equipment::FAULT);
    expect($equipment->fresh()->enabled)->toBeTrue();
});

it('rejects malformed messages without changing equipment or history', function (string $topic, string $message) {
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01', 'status' => Equipment::OFFLINE]);

    expect(fn () => app(EquipmentMessageService::class)->consume($topic, $message))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('mes_equipment_data', 0);
    expect($equipment->fresh()->status)->toBe(Equipment::OFFLINE);
})->with(function () {
    $valid = [
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '2026-11-16T04:00:00.123456+00:00',
        'status' => 'running',
        'temperature' => 60,
        'speed' => 1200,
        'pressure' => 0.7,
        'output' => 120,
    ];

    foreach ([
        'different factory' => 'mes/OTHER/EQ-SMT-01/data',
        'missing topic section' => 'mes/F001/data',
        'unknown message type' => 'mes/F001/EQ-SMT-01/config',
        'different device' => 'mes/F001/OTHER/data',
    ] as $name => $topic) {
        yield $name => [$topic, json_encode($valid, JSON_THROW_ON_ERROR)];
    }

    foreach ([
        'malformed JSON' => '{broken',
        'scalar JSON' => '42',
        'JSON list' => '[]',
    ] as $name => $message) {
        yield $name => ['mes/F001/EQ-SMT-01/data', $message];
    }

    foreach ([
        'missing temperature' => ['temperature' => null],
        'invalid status' => ['status' => 'broken'],
        'negative speed' => ['speed' => -1],
        'fractional speed' => ['speed' => 12.5],
        'invalid temperature' => ['temperature' => 'hot'],
        'negative pressure' => ['pressure' => -1],
        'negative output' => ['output' => -1],
        'overflowing output' => ['output' => 4294967296],
        'negative runtime' => ['runtime_seconds' => -1],
        'fractional runtime' => ['runtime_seconds' => 1.5],
        'overflowing runtime' => ['runtime_seconds' => 4294967296],
        'relative timestamp' => ['timestamp' => 'tomorrow'],
        'invalid calendar date' => ['timestamp' => '2026-02-30T04:00:00+00:00'],
    ] as $name => $changes) {
        yield $name => ['mes/F001/EQ-SMT-01/data', json_encode([...$valid, ...$changes], JSON_THROW_ON_ERROR)];
    }

    yield 'missing alarm details' => ['mes/F001/EQ-SMT-01/alarm', json_encode($valid, JSON_THROW_ON_ERROR)];
    yield 'invalid alarm level' => ['mes/F001/EQ-SMT-01/alarm', json_encode([
        ...$valid, 'alarm_code' => 'device_fault', 'message' => '设备故障', 'level' => 'urgent',
    ], JSON_THROW_ON_ERROR)];
});

it('accepts the earliest storable UTC date', function () {
    $this->travelTo('2026-11-16 04:00:00');
    config(['mqtt.factory' => 'F001']);
    Equipment::factory()->create(['code' => 'EQ-SMT-01']);

    $sample = app(EquipmentMessageService::class)->consume('mes/F001/EQ-SMT-01/data', json_encode([
        'device_code' => 'EQ-SMT-01',
        'timestamp' => '1000-01-01T00:00:00Z',
        'status' => Equipment::RUNNING,
        'temperature' => 30,
        'speed' => 0,
        'pressure' => 0,
        'output' => 0,
        'runtime_seconds' => 0,
    ], JSON_THROW_ON_ERROR));

    expect($sample->fresh()->recorded_at->format('Y-m-d H:i:s'))->toBe('1000-01-01 00:00:00');
    expect($sample->runtime_seconds)->toBe(0);
});

it('rejects a message for an unknown equipment code without creating equipment', function () {
    config(['mqtt.factory' => 'F001']);

    expect(fn () => app(EquipmentMessageService::class)->consume('mes/F001/UNKNOWN/data', json_encode([
        'device_code' => 'UNKNOWN',
        'timestamp' => '2026-11-16T04:00:00Z',
        'status' => Equipment::RUNNING,
        'temperature' => 60,
        'speed' => 1200,
        'pressure' => 0.7,
        'output' => 120,
    ], JSON_THROW_ON_ERROR)))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('mes_equipments', 0);
    $this->assertDatabaseCount('mes_equipment_data', 0);
});
