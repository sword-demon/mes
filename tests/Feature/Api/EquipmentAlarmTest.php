<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;

it('lists alarms filtered by equipment, level, status and trigger date', function () {
    $actor = userWithPermissions(['equipment.alarms.index'], 'equipment_alarm_reader');
    $equipment = Equipment::factory()->create();
    $otherEquipment = Equipment::factory()->create();
    $match = EquipmentAlarm::factory()->create([
        'equipment_id' => $equipment->id,
        'metric' => 'temperature',
        'level' => EquipmentAlarm::LEVEL_WARNING,
        'status' => EquipmentAlarm::CONFIRMED,
        'triggered_at' => '2026-11-16 10:00:00',
    ]);
    EquipmentAlarm::factory()->create([
        'equipment_id' => $otherEquipment->id,
        'metric' => 'speed',
        'level' => EquipmentAlarm::LEVEL_WARNING,
        'status' => EquipmentAlarm::CONFIRMED,
        'triggered_at' => '2026-11-16 10:00:00',
    ]);
    EquipmentAlarm::factory()->create([
        'equipment_id' => $equipment->id,
        'metric' => 'pressure',
        'level' => EquipmentAlarm::LEVEL_CRITICAL,
        'status' => EquipmentAlarm::CONFIRMED,
        'triggered_at' => '2026-11-16 10:00:00',
    ]);
    EquipmentAlarm::factory()->create([
        'equipment_id' => $equipment->id,
        'metric' => 'speed',
        'level' => EquipmentAlarm::LEVEL_WARNING,
        'status' => EquipmentAlarm::PENDING,
        'triggered_at' => '2026-11-16 10:00:00',
    ]);
    EquipmentAlarm::factory()->create([
        'equipment_id' => $equipment->id,
        'metric' => 'temperature',
        'level' => EquipmentAlarm::LEVEL_WARNING,
        'status' => EquipmentAlarm::CONFIRMED,
        'triggered_at' => '2026-11-15 10:00:00',
    ]);

    bearer($actor)->getJson('/api/v1/equipments/alarms?'.http_build_query([
        'equipment_id' => $equipment->id,
        'level' => EquipmentAlarm::LEVEL_WARNING,
        'status' => EquipmentAlarm::CONFIRMED,
        'start_date' => '2026-11-16',
        'end_date' => '2026-11-16',
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $match->id)
        ->assertJsonPath('data.items.0.equipment.code', $equipment->code)
        ->assertJsonPath('data.items.0.level_label', '警告')
        ->assertJsonPath('data.items.0.status_label', '已确认')
        ->assertJsonPath('data.items.0.metric_label', '温度')
        ->assertJsonPath('data.items.0.metric_unit', '℃');
});

it('orders alarms by trigger time descending', function () {
    $actor = userWithPermissions(['equipment.alarms.index'], 'equipment_alarm_order_reader');
    $equipment = Equipment::factory()->create();
    EquipmentAlarm::factory()->create([
        'equipment_id' => $equipment->id,
        'code' => 'ALM-OLD',
        'triggered_at' => '2026-11-16 08:00:00',
    ]);
    EquipmentAlarm::factory()->create([
        'equipment_id' => $equipment->id,
        'code' => 'ALM-NEW',
        'triggered_at' => '2026-11-16 09:00:00',
    ]);

    bearer($actor)->getJson('/api/v1/equipments/alarms')
        ->assertOk()
        ->assertJsonPath('data.items.0.code', 'ALM-NEW')
        ->assertJsonPath('data.items.1.code', 'ALM-OLD');
});

it('returns equipment options for the alarm filter', function () {
    $actor = userWithPermissions(['equipment.alarms.index'], 'equipment_alarm_options_reader');
    $equipment = Equipment::factory()->create([
        'code' => 'EQ-SMT-01',
        'name' => 'SMT 贴片机一号',
        'status' => Equipment::RUNNING,
    ]);

    bearer($actor)->getJson('/api/v1/equipments/alarms/options')
        ->assertOk()
        ->assertJsonPath('data.equipments.0.id', $equipment->id)
        ->assertJsonPath('data.equipments.0.code', 'EQ-SMT-01')
        ->assertJsonPath('data.equipments.0.status_label', '运行');
});

it('confirms, processes and closes an alarm while recording the handler and note', function () {
    $actor = userWithPermissions([
        'equipment.alarms.confirm',
        'equipment.alarms.process',
        'equipment.alarms.close',
    ], 'equipment_alarm_handler');
    $alarm = EquipmentAlarm::factory()->create(['status' => EquipmentAlarm::PENDING]);

    bearer($actor)->postJson('/api/v1/equipments/alarms/'.$alarm->id.'/confirm')
        ->assertOk()
        ->assertJsonPath('data.status', EquipmentAlarm::CONFIRMED)
        ->assertJsonPath('data.confirmed_by.name', $actor->name);

    bearer($actor)->postJson('/api/v1/equipments/alarms/'.$alarm->id.'/process', [
        'handling_note' => '已停机检查传动机构。',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', EquipmentAlarm::PROCESSING)
        ->assertJsonPath('data.handling_note', '已停机检查传动机构。')
        ->assertJsonPath('data.handled_by.name', $actor->name);

    bearer($actor)->postJson('/api/v1/equipments/alarms/'.$alarm->id.'/close', [
        'handling_note' => '更换轴承后复测正常。',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', EquipmentAlarm::CLOSED)
        ->assertJsonPath('data.handling_note', '更换轴承后复测正常。')
        ->assertJsonPath('data.closed_at', fn (string $value) => $value !== '');

    $alarm->refresh();

    expect($alarm->handled_at)->not->toBeNull()
        ->and($alarm->closed_at)->not->toBeNull();
});

it('keeps the processing note when closing without a new note', function () {
    $actor = userWithPermissions(['equipment.alarms.close'], 'equipment_alarm_close_only');
    $alarm = EquipmentAlarm::factory()->create([
        'status' => EquipmentAlarm::PROCESSING,
        'handling_note' => '等待备件到货。',
    ]);

    bearer($actor)->postJson('/api/v1/equipments/alarms/'.$alarm->id.'/close')
        ->assertOk()
        ->assertJsonPath('data.status', EquipmentAlarm::CLOSED)
        ->assertJsonPath('data.handling_note', '等待备件到货。');
});

it('enforces the alarm state machine and requires a processing note', function () {
    $actor = userWithPermissions([
        'equipment.alarms.confirm',
        'equipment.alarms.process',
        'equipment.alarms.close',
    ], 'equipment_alarm_state_machine');
    $pending = EquipmentAlarm::factory()->create(['status' => EquipmentAlarm::PENDING]);
    $confirmed = EquipmentAlarm::factory()->create(['status' => EquipmentAlarm::CONFIRMED]);
    $processing = EquipmentAlarm::factory()->create(['status' => EquipmentAlarm::PROCESSING]);

    bearer($actor)->postJson('/api/v1/equipments/alarms/'.$pending->id.'/process', [
        'handling_note' => '处理中',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有已确认的设备告警可以处理');

    bearer($actor)->postJson('/api/v1/equipments/alarms/'.$confirmed->id.'/close')
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有处理中的设备告警可以关闭');

    bearer($actor)->postJson('/api/v1/equipments/alarms/'.$processing->id.'/confirm')
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有待确认的设备告警可以确认');

    bearer($actor)->postJson('/api/v1/equipments/alarms/'.$confirmed->id.'/process')
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.handling_note.0', '请填写处理说明');
});

it('validates alarm list filters', function (string $query, string $field) {
    bearer(userWithPermissions(['equipment.alarms.index'], 'equipment_alarm_invalid_'.md5($query)))
        ->getJson('/api/v1/equipments/alarms?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field], 'data.errors');
})->with([
    'unknown level' => ['level=urgent', 'level'],
    'unknown status' => ['status=open', 'status'],
    'invalid start date' => ['start_date=2026-13-01', 'start_date'],
    'end before start' => ['start_date=2026-11-16&end_date=2026-11-15', 'end_date'],
]);

it('requires permissions for listing and handling alarms', function (string $method, string $uri) {
    $alarm = EquipmentAlarm::factory()->create();

    bearer(userWithPermissions([], 'equipment_alarm_denied_'.md5($method.$uri)))
        ->json($method, str_replace('{alarm}', (string) $alarm->id, $uri))
        ->assertForbidden()
        ->assertJsonPath('code', 403);
})->with([
    'list' => ['GET', '/api/v1/equipments/alarms'],
    'confirm' => ['POST', '/api/v1/equipments/alarms/{alarm}/confirm'],
    'process' => ['POST', '/api/v1/equipments/alarms/{alarm}/process'],
    'close' => ['POST', '/api/v1/equipments/alarms/{alarm}/close'],
]);

it('requires authentication for alarm endpoints', function (string $method, string $uri) {
    $this->json($method, $uri)
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
})->with([
    'list' => ['GET', '/api/v1/equipments/alarms'],
    'confirm' => ['POST', '/api/v1/equipments/alarms/1/confirm'],
]);
