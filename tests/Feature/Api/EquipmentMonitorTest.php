<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;

it('returns each enabled equipment with its latest data sample and status totals', function () {
    $actor = userWithPermissions(['equipment.monitor.index']);
    $workshop = Workshop::factory()->create();
    $line = ProductionLine::factory()->create(['workshop_id' => $workshop->id]);
    $running = Equipment::factory()->create([
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'status' => Equipment::RUNNING,
    ]);
    Equipment::factory()->create([
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'status' => Equipment::FAULT,
    ]);
    Equipment::factory()->create([
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'status' => Equipment::RUNNING,
        'enabled' => false,
    ]);
    EquipmentData::factory()->create([
        'equipment_id' => $running->id,
        'message_type' => 'data',
        'recorded_at' => '2026-11-16 05:00:00',
        'status' => Equipment::RUNNING,
        'temperature' => 66.5,
        'speed' => 1320,
        'pressure' => 0.82,
        'output' => 912,
        'runtime_seconds' => 3600,
    ]);
    EquipmentData::factory()->create([
        'equipment_id' => $running->id,
        'message_type' => 'status',
        'recorded_at' => '2026-11-16 06:00:00',
        'status' => Equipment::FAULT,
        'temperature' => 99,
    ]);
    EquipmentData::factory()->create([
        'equipment_id' => $running->id,
        'message_type' => 'data',
        'recorded_at' => '2026-11-16 04:00:00',
        'temperature' => 10,
    ]);

    bearer($actor)->getJson('/api/v1/equipments/monitor')
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $running->id)
        ->assertJsonPath('data.items.0.status', Equipment::RUNNING)
        ->assertJsonPath('data.items.0.status_label', '运行')
        ->assertJsonPath('data.items.0.latest_data.recorded_at', '2026-11-16T05:00:00.000000Z')
        ->assertJsonPath('data.items.0.latest_data.temperature', 66.5)
        ->assertJsonPath('data.items.0.latest_data.speed', 1320)
        ->assertJsonPath('data.items.0.latest_data.pressure', 0.82)
        ->assertJsonPath('data.items.0.latest_data.output', 912)
        ->assertJsonPath('data.items.0.latest_data.runtime_seconds', 3600)
        ->assertJsonPath('data.items.1.latest_data', null)
        ->assertJsonPath('data.status_counts.running', 1)
        ->assertJsonPath('data.status_counts.idle', 0)
        ->assertJsonPath('data.status_counts.fault', 1)
        ->assertJsonPath('data.status_counts.repair', 0)
        ->assertJsonPath('data.status_counts.offline', 0);
});

it('returns only data samples inside the requested history range', function () {
    $actor = userWithPermissions(['equipment.history.index']);
    $equipment = Equipment::factory()->create([
        'code' => 'EQ-SMT-01',
        'name' => 'SMT 贴片机一号',
        'status' => Equipment::RUNNING,
    ]);
    EquipmentData::factory()->create([
        'equipment_id' => $equipment->id,
        'message_type' => 'data',
        'recorded_at' => '2026-11-16 03:59:59',
    ]);
    $first = EquipmentData::factory()->create([
        'equipment_id' => $equipment->id,
        'message_type' => 'data',
        'recorded_at' => '2026-11-16 04:00:00',
        'temperature' => 61.2,
        'speed' => 1200,
        'pressure' => 0.71,
        'output' => 100,
    ]);
    $second = EquipmentData::factory()->create([
        'equipment_id' => $equipment->id,
        'message_type' => 'data',
        'recorded_at' => '2026-11-16 04:30:00',
        'temperature' => 62.3,
        'speed' => 1250,
        'pressure' => 0.73,
        'output' => 130,
    ]);
    $last = EquipmentData::factory()->create([
        'equipment_id' => $equipment->id,
        'message_type' => 'data',
        'recorded_at' => '2026-11-16 05:00:00',
        'temperature' => 63.4,
        'speed' => 1300,
        'pressure' => 0.75,
        'output' => 160,
    ]);
    EquipmentData::factory()->create([
        'equipment_id' => $equipment->id,
        'message_type' => 'status',
        'recorded_at' => '2026-11-16 04:15:00',
        'temperature' => 99,
    ]);
    EquipmentData::factory()->create([
        'equipment_id' => $equipment->id,
        'message_type' => 'alarm',
        'recorded_at' => '2026-11-16 04:20:00',
        'temperature' => 98,
    ]);
    EquipmentData::factory()->create([
        'equipment_id' => Equipment::factory()->create()->id,
        'message_type' => 'data',
        'recorded_at' => '2026-11-16 04:30:00',
        'temperature' => 77,
    ]);

    bearer($actor)->getJson('/api/v1/equipments/'.$equipment->id.'/history?'.http_build_query([
        'start_at' => '2026-11-16T04:00:00Z',
        'end_at' => '2026-11-16T05:00:00Z',
    ]))
        ->assertOk()
        ->assertJsonPath('data.equipment.code', 'EQ-SMT-01')
        ->assertJsonCount(3, 'data.items')
        ->assertJsonPath('data.items.0.id', $first->id)
        ->assertJsonPath('data.items.0.recorded_at', '2026-11-16T04:00:00.000000Z')
        ->assertJsonPath('data.items.0.temperature', 61.2)
        ->assertJsonPath('data.items.1.id', $second->id)
        ->assertJsonPath('data.items.1.recorded_at', '2026-11-16T04:30:00.000000Z')
        ->assertJsonPath('data.items.2.id', $last->id)
        ->assertJsonPath('data.items.2.output', 160);

});

it('rejects a history range longer than 24 hours', function () {
    $actor = userWithPermissions(['equipment.history.index']);
    $equipment = Equipment::factory()->create();

    bearer($actor)->getJson('/api/v1/equipments/'.$equipment->id.'/history?'.http_build_query([
        'start_at' => '2026-11-15T04:00:00Z',
        'end_at' => '2026-11-16T04:00:01Z',
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', '查询时间区间不能超过 24 小时');
});

it('rejects a history range whose end is not after its start', function () {
    $actor = userWithPermissions(['equipment.history.index']);
    $equipment = Equipment::factory()->create();

    bearer($actor)->getJson('/api/v1/equipments/'.$equipment->id.'/history?'.http_build_query([
        'start_at' => '2026-11-16T05:00:00Z',
        'end_at' => '2026-11-16T04:00:00Z',
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.end_at.0', '结束时间必须晚于开始时间');
});

it('forbids monitoring and history without their permissions', function (string $uri) {
    Equipment::factory()->create();

    bearer(userWithPermissions([]))->getJson($uri)->assertForbidden();
})->with([
    '/api/v1/equipments/monitor',
    '/api/v1/equipments/1/history?start_at=2026-11-16T04%3A00%3A00Z&end_at=2026-11-16T05%3A00%3A00Z',
]);

it('requires authentication for monitoring and history', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized()->assertJsonPath('code', 401);
})->with([
    '/api/v1/equipments/monitor',
    '/api/v1/equipments/1/history?start_at=2026-11-16T04%3A00%3A00Z&end_at=2026-11-16T05%3A00%3A00Z',
]);
