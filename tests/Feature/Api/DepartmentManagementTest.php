<?php

use App\Modules\Organization\Model\Employee;
use App\Modules\System\Model\Department;
use App\Modules\System\Model\User;

it('returns the department tree with the department list permission', function () {
    $actor = userWithPermissions(['system.departments.index']);
    $root = Department::factory()->create(['code' => 'root', 'name' => '演示工厂', 'sort' => 1]);
    Department::factory()->create(['code' => 'quality', 'name' => '质量部', 'parent_id' => $root->id, 'sort' => 1]);

    bearer($actor)->getJson('/api/v1/system/departments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', '演示工厂')
        ->assertJsonPath('data.0.children.0.name', '质量部');
});

it('returns 403 when listing departments without the permission', function () {
    bearer(userWithPermissions([]))->getJson('/api/v1/system/departments')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('creates a department under an existing parent', function () {
    $actor = userWithPermissions(['system.departments.create']);
    $parent = Department::factory()->create(['code' => 'production']);

    bearer($actor)->postJson('/api/v1/system/departments', [
        'parent_id' => $parent->id,
        'code' => 'workshop-c',
        'name' => '三号车间',
    ])->assertOk()
        ->assertJsonPath('data.code', 'workshop-c')
        ->assertJsonPath('data.parent_id', $parent->id);
});

it('returns 422 when the department code is already taken', function () {
    $actor = userWithPermissions(['system.departments.create']);
    Department::factory()->create(['code' => 'duplicated']);

    bearer($actor)->postJson('/api/v1/system/departments', [
        'code' => 'duplicated', 'name' => '重复部门',
    ])->assertUnprocessable()->assertJsonPath('code', 422);
});

it('renames a department', function () {
    $actor = userWithPermissions(['system.departments.update']);
    $department = Department::factory()->create(['name' => '旧部门']);

    bearer($actor)->putJson("/api/v1/system/departments/{$department->id}", ['name' => '新部门'])
        ->assertOk()
        ->assertJsonPath('data.name', '新部门');
});

it('deletes an empty department', function () {
    $actor = userWithPermissions(['system.departments.delete']);
    $department = Department::factory()->create();

    bearer($actor)->deleteJson("/api/v1/system/departments/{$department->id}")->assertOk();

    expect(Department::query()->whereKey($department->id)->exists())->toBeFalse();
});

it('returns 403 when deleting a department that still has users', function () {
    $actor = userWithPermissions(['system.departments.delete']);
    $department = Department::factory()->create();
    User::factory()->create(['department_id' => $department->id]);

    bearer($actor)->deleteJson("/api/v1/system/departments/{$department->id}")
        ->assertForbidden()->assertJsonPath('code', 403);

    expect(Department::query()->whereKey($department->id)->exists())->toBeTrue();
});

it('returns 403 when deleting a department that still has children', function () {
    $actor = userWithPermissions(['system.departments.delete']);
    $parent = Department::factory()->create();
    Department::factory()->create(['parent_id' => $parent->id]);

    bearer($actor)->deleteJson("/api/v1/system/departments/{$parent->id}")
        ->assertForbidden()->assertJsonPath('code', 403);
});

it('returns 403 when deleting a department that still has employees', function () {
    $actor = userWithPermissions(['system.departments.delete']);
    $department = Department::factory()->create();
    Employee::factory()->create(['department_id' => $department->id, 'team_id' => null]);

    bearer($actor)->deleteJson("/api/v1/system/departments/{$department->id}")
        ->assertForbidden()->assertJsonPath('message', '部门下还有员工，不能删除');

    expect(Department::query()->whereKey($department->id)->exists())->toBeTrue();
});
