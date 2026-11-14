<?php

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Model\Workshop;
use App\Modules\System\Model\Department;

it('lists factories for a user holding the factory list permission', function () {
    $actor = userWithPermissions(['basic.factories.index']);
    Factory::factory()->create(['code' => 'FAC01', 'name' => '演示工厂']);

    bearer($actor)->getJson('/api/v1/basic-data/factories')
        ->assertOk()
        ->assertJsonPath('data.items.0.code', 'FAC01')
        ->assertJsonPath('data.meta.total', 1);
});

it('returns 403 when listing factories without the permission', function () {
    bearer(userWithPermissions([]))->getJson('/api/v1/basic-data/factories')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('creates a factory', function () {
    $actor = userWithPermissions(['basic.factories.create']);

    bearer($actor)->postJson('/api/v1/basic-data/factories', [
        'code' => 'FAC02',
        'name' => '二号工厂',
        'address' => '苏州工业园区',
        'contact' => '张三',
        'phone' => '13800000000',
    ])->assertOk()
        ->assertJsonPath('data.code', 'FAC02')
        ->assertJsonPath('data.status', true);
});

it('returns a clear error when the factory code is already taken', function () {
    $actor = userWithPermissions(['basic.factories.create']);
    Factory::factory()->create(['code' => 'FAC01']);

    bearer($actor)->postJson('/api/v1/basic-data/factories', [
        'code' => 'FAC01', 'name' => '重复工厂',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '编码已存在');
});

it('disables a factory through update', function () {
    $actor = userWithPermissions(['basic.factories.update']);
    $factory = Factory::factory()->create(['status' => true]);

    bearer($actor)->putJson("/api/v1/basic-data/factories/{$factory->id}", ['status' => false])
        ->assertOk()
        ->assertJsonPath('data.status', false);

    expect($factory->refresh()->status)->toBeFalse();
});

it('filters workshops by factory and keeps disabled ones out of the active list', function () {
    $actor = userWithPermissions(['basic.workshops.index']);
    $factory = Factory::factory()->create(['name' => '演示工厂']);
    $other = Factory::factory()->create();
    Workshop::factory()->create(['factory_id' => $factory->id, 'code' => 'WS01']);
    Workshop::factory()->create(['factory_id' => $other->id, 'code' => 'WS02']);
    Workshop::factory()->create(['factory_id' => $factory->id, 'code' => 'WS03', 'status' => false]);

    bearer($actor)->getJson("/api/v1/basic-data/workshops?factory_id={$factory->id}&status=1")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'WS01')
        ->assertJsonPath('data.items.0.factory.name', '演示工厂');
});

it('creates a workshop under an existing factory', function () {
    $actor = userWithPermissions(['basic.workshops.create']);
    $factory = Factory::factory()->create();

    bearer($actor)->postJson('/api/v1/basic-data/workshops', [
        'factory_id' => $factory->id,
        'code' => 'WS09',
        'name' => '九号车间',
        'manager' => '李四',
    ])->assertOk()
        ->assertJsonPath('data.factory_id', $factory->id);
});

it('refuses to hang a new workshop under a disabled factory', function () {
    $actor = userWithPermissions(['basic.workshops.create']);
    $factory = Factory::factory()->create(['status' => false]);

    bearer($actor)->postJson('/api/v1/basic-data/workshops', [
        'factory_id' => $factory->id,
        'code' => 'WS10',
        'name' => '十号车间',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '所属工厂不存在或已停用');
});

it('filters production lines by workshop', function () {
    $actor = userWithPermissions(['basic.lines.index']);
    $workshop = Workshop::factory()->create();
    ProductionLine::factory()->create(['workshop_id' => $workshop->id, 'code' => 'PL01', 'capacity' => 120]);
    ProductionLine::factory()->create(['code' => 'PL02']);

    bearer($actor)->getJson("/api/v1/basic-data/production-lines?workshop_id={$workshop->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'PL01')
        ->assertJsonPath('data.items.0.capacity', 120);
});

it('creates a production line with capacity', function () {
    $actor = userWithPermissions(['basic.lines.create']);
    $workshop = Workshop::factory()->create();

    bearer($actor)->postJson('/api/v1/basic-data/production-lines', [
        'workshop_id' => $workshop->id,
        'code' => 'PL09',
        'name' => '九号线',
        'capacity' => 90,
    ])->assertOk()
        ->assertJsonPath('data.capacity', 90);
});

it('refuses to hang a new line or team under a disabled workshop', function () {
    $actor = userWithPermissions(['basic.lines.create', 'basic.teams.create']);
    $workshop = Workshop::factory()->create(['status' => false]);

    bearer($actor)->postJson('/api/v1/basic-data/production-lines', [
        'workshop_id' => $workshop->id, 'code' => 'PL10', 'name' => '十号线',
    ])->assertUnprocessable()->assertJsonPath('message', '所属车间不存在或已停用');

    bearer($actor)->postJson('/api/v1/basic-data/teams', [
        'workshop_id' => $workshop->id, 'code' => 'TEAM10', 'name' => 'C班',
    ])->assertUnprocessable()->assertJsonPath('message', '所属车间不存在或已停用');
});

it('hides children of a disabled parent from the enabled dropdown', function () {
    $actor = userWithPermissions(['basic.lines.index', 'basic.teams.index']);
    $factory = Factory::factory()->create();
    $active = Workshop::factory()->create(['factory_id' => $factory->id]);
    $closed = Workshop::factory()->create(['factory_id' => $factory->id, 'status' => false]);
    ProductionLine::factory()->create(['workshop_id' => $active->id, 'code' => 'PL21']);
    ProductionLine::factory()->create(['workshop_id' => $closed->id, 'code' => 'PL22']);
    Team::factory()->create(['workshop_id' => $closed->id, 'code' => 'TEAM22']);

    bearer($actor)->getJson('/api/v1/basic-data/production-lines?status=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'PL21');

    bearer($actor)->getJson('/api/v1/basic-data/teams?status=1')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

it('updates a production line through its route binding', function () {
    $actor = userWithPermissions(['basic.lines.update']);
    $line = ProductionLine::factory()->create(['capacity' => 60]);

    bearer($actor)->putJson("/api/v1/basic-data/production-lines/{$line->id}", ['capacity' => 150])
        ->assertOk()
        ->assertJsonPath('data.capacity', 150);
});

it('filters teams by workshop', function () {
    $actor = userWithPermissions(['basic.teams.index']);
    $workshop = Workshop::factory()->create();
    Team::factory()->create(['workshop_id' => $workshop->id, 'code' => 'TEAM01']);
    Team::factory()->create(['code' => 'TEAM02']);

    bearer($actor)->getJson("/api/v1/basic-data/teams?workshop_id={$workshop->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'TEAM01');
});

it('lists employees with their department and team', function () {
    $actor = userWithPermissions(['basic.employees.index']);
    $department = Department::factory()->create(['code' => 'production', 'name' => '生产部']);
    $team = Team::factory()->create(['name' => 'A班']);
    Employee::factory()->create([
        'employee_no' => 'EMP001',
        'department_id' => $department->id,
        'team_id' => $team->id,
        'skill_level' => '中级',
    ]);

    bearer($actor)->getJson("/api/v1/basic-data/employees?team_id={$team->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.employee_no', 'EMP001')
        ->assertJsonPath('data.items.0.department.name', '生产部')
        ->assertJsonPath('data.items.0.team.name', 'A班')
        ->assertJsonPath('data.items.0.skill_level', '中级');
});

it('finds an employee by employee number through the keyword filter', function () {
    $actor = userWithPermissions(['basic.employees.index']);
    Employee::factory()->create(['employee_no' => 'EMP777', 'name' => '查找目标']);
    Employee::factory()->create(['employee_no' => 'EMP888']);

    bearer($actor)->getJson('/api/v1/basic-data/employees?keyword=EMP777')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.employee_no', 'EMP777');
});

it('filters employees by department', function () {
    $actor = userWithPermissions(['basic.employees.index']);
    $production = Department::factory()->create(['code' => 'production']);
    $quality = Department::factory()->create(['code' => 'quality']);
    Employee::factory()->create(['employee_no' => 'EMP101', 'department_id' => $production->id]);
    Employee::factory()->create(['employee_no' => 'EMP102', 'department_id' => $quality->id]);

    bearer($actor)->getJson("/api/v1/basic-data/employees?department_id={$quality->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.employee_no', 'EMP102');
});

it('serializes an employee with no department or team as null relations', function () {
    $actor = userWithPermissions(['basic.employees.index']);
    Employee::factory()->create(['employee_no' => 'EMP000', 'department_id' => null, 'team_id' => null]);

    bearer($actor)->getJson('/api/v1/basic-data/employees')
        ->assertOk()
        ->assertJsonPath('data.items.0.department', null)
        ->assertJsonPath('data.items.0.team', null);
});

it('returns a clear error when the employee number is already taken', function () {
    $actor = userWithPermissions(['basic.employees.create']);
    Employee::factory()->create(['employee_no' => 'EMP001']);

    bearer($actor)->postJson('/api/v1/basic-data/employees', [
        'employee_no' => 'EMP001', 'name' => '重复员工',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '工号已存在');
});

it('caps the page size so a dropdown request cannot dump the table', function () {
    $actor = userWithPermissions(['basic.factories.index']);
    Factory::factory()->count(3)->create();

    bearer($actor)->getJson('/api/v1/basic-data/factories?per_page=500')
        ->assertOk()
        ->assertJsonPath('data.meta.per_page', 100);
});

it('deletes a factory that has no workshops yet', function () {
    $actor = userWithPermissions(['basic.factories.delete']);
    $factory = Factory::factory()->create();

    bearer($actor)->deleteJson("/api/v1/basic-data/factories/{$factory->id}")->assertOk();

    expect(Factory::query()->whereKey($factory->id)->exists())->toBeFalse();
});

it('refuses to delete a factory that still owns workshops', function () {
    $actor = userWithPermissions(['basic.factories.delete']);
    $factory = Factory::factory()->create();
    Workshop::factory()->create(['factory_id' => $factory->id]);

    bearer($actor)->deleteJson("/api/v1/basic-data/factories/{$factory->id}")
        ->assertForbidden()
        ->assertJsonPath('message', '工厂下还有车间，只能停用');

    expect(Factory::query()->whereKey($factory->id)->exists())->toBeTrue();
});

it('refuses to delete a workshop, line or team that still has children', function () {
    $actor = userWithPermissions([
        'basic.workshops.delete', 'basic.lines.delete', 'basic.teams.delete',
    ]);
    $workshop = Workshop::factory()->create();
    $line = ProductionLine::factory()->create(['workshop_id' => $workshop->id]);
    $team = Team::factory()->create(['workshop_id' => $workshop->id]);
    Employee::factory()->create(['team_id' => $team->id]);

    bearer($actor)->deleteJson("/api/v1/basic-data/workshops/{$workshop->id}")
        ->assertForbidden()->assertJsonPath('message', '车间下还有产线，只能停用');

    bearer($actor)->deleteJson("/api/v1/basic-data/teams/{$team->id}")
        ->assertForbidden()->assertJsonPath('message', '班组下还有员工，只能停用');

    // An empty line is the one case that may be removed outright.
    bearer($actor)->deleteJson("/api/v1/basic-data/production-lines/{$line->id}")->assertOk();
    expect(ProductionLine::query()->whereKey($line->id)->exists())->toBeFalse();
});

it('requires the delete permission to remove organization data', function () {
    $actor = userWithPermissions(['basic.factories.update']);
    $factory = Factory::factory()->create();

    bearer($actor)->deleteJson("/api/v1/basic-data/factories/{$factory->id}")
        ->assertForbidden();

    expect(Factory::query()->whereKey($factory->id)->exists())->toBeTrue();
});
