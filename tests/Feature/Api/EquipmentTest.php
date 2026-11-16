<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;

it('creates, edits and stops equipment with its workshop, line and owner', function () {
    $actor = userWithPermissions(['equipment.ledger.create', 'equipment.ledger.update']);
    $workshop = Workshop::factory()->create(['code' => 'WS01']);
    $line = ProductionLine::factory()->create(['workshop_id' => $workshop->id, 'code' => 'PL01']);
    $owner = Employee::factory()->create(['employee_no' => 'EMP001']);

    $created = bearer($actor)->postJson('/api/v1/equipments', [
        'code' => 'EQ-SMT-01',
        'name' => 'SMT 贴片机一号',
        'type' => 'SMT 贴片机',
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'model' => 'SMT-X200',
        'status' => Equipment::RUNNING,
        'owner_id' => $owner->id,
    ])->assertOk()
        ->assertJsonPath('data.code', 'EQ-SMT-01')
        ->assertJsonPath('data.status', Equipment::RUNNING)
        ->assertJsonPath('data.status_label', '运行')
        ->assertJsonPath('data.workshop.code', 'WS01')
        ->assertJsonPath('data.production_line.code', 'PL01')
        ->assertJsonPath('data.owner.employee_no', 'EMP001')
        ->json('data');

    bearer($actor)->putJson("/api/v1/equipments/{$created['id']}", [
        'name' => 'SMT 贴片机二号',
        'status' => Equipment::OFFLINE,
        'enabled' => false,
    ])->assertOk()
        ->assertJsonPath('data.name', 'SMT 贴片机二号')
        ->assertJsonPath('data.status', Equipment::OFFLINE)
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.status_label', '离线');

    $this->assertDatabaseHas('mes_equipments', [
        'id' => $created['id'],
        'code' => 'EQ-SMT-01',
        'name' => 'SMT 贴片机二号',
        'status' => Equipment::OFFLINE,
        'enabled' => false,
        'owner_id' => $owner->id,
    ]);

    bearer($actor)->putJson("/api/v1/equipments/{$created['id']}", [
        'enabled' => true,
        'status' => Equipment::IDLE,
    ])->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.status', Equipment::IDLE);
});

it('rejects a production line that belongs to another workshop', function () {
    $actor = userWithPermissions(['equipment.ledger.create']);
    $firstWorkshop = Workshop::factory()->create();
    $secondWorkshop = Workshop::factory()->create();
    $foreignLine = ProductionLine::factory()->create(['workshop_id' => $secondWorkshop->id]);
    $owner = Employee::factory()->create();

    bearer($actor)->postJson('/api/v1/equipments', [
        'code' => 'EQ-BAD-01',
        'name' => '错配设备',
        'type' => 'CNC 加工中心',
        'workshop_id' => $firstWorkshop->id,
        'production_line_id' => $foreignLine->id,
        'model' => 'CNC-850',
        'owner_id' => $owner->id,
    ])->assertStatus(422)
        ->assertJsonPath('message', '所属产线不属于所选车间');

    expect(Equipment::query()->count())->toBe(0);
});

it('rejects updating equipment onto a line from another workshop', function () {
    $actor = userWithPermissions(['equipment.ledger.update']);
    $firstWorkshop = Workshop::factory()->create();
    $secondWorkshop = Workshop::factory()->create();
    $line = ProductionLine::factory()->create(['workshop_id' => $firstWorkshop->id]);
    $owner = Employee::factory()->create();
    $equipment = Equipment::factory()->create([
        'workshop_id' => $firstWorkshop->id,
        'production_line_id' => $line->id,
        'owner_id' => $owner->id,
    ]);

    bearer($actor)->putJson("/api/v1/equipments/{$equipment->id}", [
        'workshop_id' => $secondWorkshop->id,
    ])->assertStatus(422)
        ->assertJsonPath('message', '所属产线不属于所选车间');

    expect($equipment->refresh()->workshop_id)->toBe($firstWorkshop->id);
});

it('filters the ledger by workshop, line, type, status and keyword', function () {
    $actor = userWithPermissions(['equipment.ledger.index']);
    $firstWorkshop = Workshop::factory()->create();
    $secondWorkshop = Workshop::factory()->create();
    $firstLine = ProductionLine::factory()->create(['workshop_id' => $firstWorkshop->id]);
    $secondLine = ProductionLine::factory()->create(['workshop_id' => $secondWorkshop->id]);
    $owner = Employee::factory()->create();
    $match = Equipment::factory()->create([
        'code' => 'EQ-CNC-01',
        'name' => 'CNC 加工中心一号',
        'type' => 'CNC 加工中心',
        'workshop_id' => $firstWorkshop->id,
        'production_line_id' => $firstLine->id,
        'status' => Equipment::RUNNING,
        'owner_id' => $owner->id,
    ]);
    Equipment::factory()->create([
        'code' => 'EQ-SMT-01',
        'type' => 'SMT 贴片机',
        'workshop_id' => $secondWorkshop->id,
        'production_line_id' => $secondLine->id,
        'status' => Equipment::IDLE,
        'owner_id' => $owner->id,
    ]);

    bearer($actor)->getJson('/api/v1/equipments?'.http_build_query([
        'workshop_id' => $firstWorkshop->id,
        'production_line_id' => $firstLine->id,
        'type' => 'CNC 加工中心',
        'status' => Equipment::RUNNING,
        'keyword' => 'EQ-CNC',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $match->id)
        ->assertJsonPath('data.items.0.workshop.id', $firstWorkshop->id)
        ->assertJsonPath('data.items.0.production_line.id', $firstLine->id)
        ->assertJsonPath('data.items.0.owner.id', $owner->id);
});

it('serves available equipment options to report and flow users', function () {
    $workshop = Workshop::factory()->create();
    $line = ProductionLine::factory()->create(['workshop_id' => $workshop->id]);
    $owner = Employee::factory()->create();
    Equipment::factory()->create([
        'code' => 'EQ-RUN-01',
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'status' => Equipment::RUNNING,
        'owner_id' => $owner->id,
    ]);
    Equipment::factory()->create([
        'code' => 'EQ-IDLE-01',
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'status' => Equipment::IDLE,
        'owner_id' => $owner->id,
    ]);
    Equipment::factory()->create([
        'code' => 'EQ-FAULT-01',
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'status' => Equipment::FAULT,
        'owner_id' => $owner->id,
    ]);
    Equipment::factory()->create([
        'code' => 'EQ-DISABLED-01',
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'status' => Equipment::RUNNING,
        'enabled' => false,
        'owner_id' => $owner->id,
    ]);

    bearer(userWithPermissions(['production.flows.start']))->getJson('/api/v1/equipments/options')
        ->assertOk()
        ->assertJsonCount(2, 'data.equipments')
        ->assertJsonPath('data.equipments.0.code', 'EQ-RUN-01')
        ->assertJsonPath('data.equipments.1.code', 'EQ-IDLE-01')
        ->assertJsonPath('data.workshops.0.id', $workshop->id)
        ->assertJsonPath('data.production_lines.0.id', $line->id)
        ->assertJsonPath('data.employees.0.id', $owner->id);
});

it('validates the equipment payload', function () {
    $actor = userWithPermissions(['equipment.ledger.create']);
    Equipment::factory()->create(['code' => 'EQ-DUP-01']);
    $workshop = Workshop::factory()->create();
    $line = ProductionLine::factory()->create(['workshop_id' => $workshop->id]);
    $owner = Employee::factory()->create();
    $payload = [
        'code' => 'EQ-NEW-01',
        'name' => '新设备',
        'type' => 'CNC 加工中心',
        'workshop_id' => $workshop->id,
        'production_line_id' => $line->id,
        'model' => 'CNC-850',
        'status' => Equipment::IDLE,
        'owner_id' => $owner->id,
    ];

    bearer($actor)->postJson('/api/v1/equipments', [...$payload, 'code' => 'EQ-DUP-01'])
        ->assertUnprocessable();
    bearer($actor)->postJson('/api/v1/equipments', [...$payload, 'type' => '未知设备'])
        ->assertUnprocessable();
    bearer($actor)->postJson('/api/v1/equipments', [...$payload, 'status' => 'broken'])
        ->assertUnprocessable();

    $withoutOwner = $payload;
    unset($withoutOwner['owner_id']);
    bearer($actor)->postJson('/api/v1/equipments', $withoutOwner)
        ->assertUnprocessable();

    expect(Equipment::query()->count())->toBe(1);
});

it('returns 403 without the matching equipment permission', function (string $method, string $uri) {
    Equipment::factory()->create();

    bearer(userWithPermissions([], 'equipment_denied_'.$method))->json($method, $uri, [
        'code' => 'EQ-DENIED',
        'name' => '无权限设备',
        'type' => 'CNC 加工中心',
        'workshop_id' => 1,
        'production_line_id' => 1,
        'model' => 'X',
        'owner_id' => 1,
    ])->assertForbidden();
})->with([
    ['GET', '/api/v1/equipments'],
    ['GET', '/api/v1/equipments/options'],
    ['POST', '/api/v1/equipments'],
    ['PUT', '/api/v1/equipments/1'],
]);

it('requires authentication for equipment endpoints', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('code', 401);
})->with([
    ['GET', '/api/v1/equipments'],
    ['GET', '/api/v1/equipments/options'],
    ['POST', '/api/v1/equipments'],
    ['PUT', '/api/v1/equipments/1'],
]);
