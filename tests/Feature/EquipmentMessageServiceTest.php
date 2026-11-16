<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Equipment\Service\EquipmentMessageService;
use Illuminate\Validation\ValidationException;

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
    $this->assertDatabaseHas('mes_equipment_data', [
        'equipment_id' => $equipment->id,
        'message_type' => 'data',
        'status' => Equipment::RUNNING,
        'output' => 120,
    ]);
    expect($equipment->fresh()->status)->toBe(Equipment::RUNNING);
    expect($equipment->fresh()->enabled)->toBeFalse();
});

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
