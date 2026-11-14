<?php

use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;

it('creates a role with permissions and deletes it while unused', function () {
    $permission = Permission::factory()->create(['code' => 'system.users.index', 'type' => 'api']);
    $admin = userWithPermissions(['system.roles.create', 'system.roles.delete', 'system.users.index']);

    $created = bearer($admin)->postJson('/api/v1/system/roles', [
        'code' => 'quality_inspector',
        'name' => '质检员',
        'description' => '负责质量检验',
        'permission_ids' => [$permission->id],
    ])->assertOk()
        ->assertJsonPath('data.code', 'quality_inspector')
        ->assertJsonCount(1, 'data.permissions')
        ->json('data.id');

    bearer($admin)->deleteJson("/api/v1/system/roles/{$created}")->assertOk();

    expect(Role::query()->whereKey($created)->exists())->toBeFalse();
});

it('returns 403 when deleting a role that is still assigned', function () {
    $admin = userWithPermissions(['system.roles.delete']);
    $role = Role::factory()->create();
    User::factory()->create()->roles()->attach($role);

    bearer($admin)->deleteJson("/api/v1/system/roles/{$role->id}")
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('returns 403 when disabling, deleting or renaming the super admin role', function () {
    $admin = userWithPermissions(['system.roles.update', 'system.roles.delete']);
    $superAdmin = Role::factory()->create(['code' => Role::SUPER_ADMIN]);

    bearer($admin)->putJson("/api/v1/system/roles/{$superAdmin->id}", ['status' => false])
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    bearer($admin)->deleteJson("/api/v1/system/roles/{$superAdmin->id}")
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    bearer($admin)->putJson("/api/v1/system/roles/{$superAdmin->id}", ['code' => 'renamed'])
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    expect($superAdmin->fresh())
        ->status->toBeTrue()
        ->code->toBe(Role::SUPER_ADMIN);
});

it('lists roles and updates the name, status and permissions of one', function () {
    $role = Role::factory()->create(['name' => '旧名称']);
    $keep = Permission::factory()->create(['code' => 'system.users.index', 'type' => 'api']);
    $drop = Permission::factory()->create(['code' => 'system.users.create', 'type' => 'api']);
    $role->permissions()->attach([$keep->id, $drop->id]);
    $admin = userWithPermissions([
        'system.roles.index',
        'system.roles.update',
        'system.users.index',
        'system.users.create',
    ]);

    bearer($admin)->getJson('/api/v1/system/roles')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2);

    bearer($admin)->putJson("/api/v1/system/roles/{$role->id}", [
        'name' => '新名称',
        'status' => false,
        'permission_ids' => [$keep->id],
    ])->assertOk()
        ->assertJsonPath('data.name', '新名称')
        ->assertJsonPath('data.status', false)
        ->assertJsonCount(1, 'data.permissions')
        ->assertJsonPath('data.permissions.0.code', 'system.users.index');

    $fresh = $role->fresh();

    expect($fresh->name)->toBe('新名称')
        ->and($fresh->status)->toBeFalse()
        ->and($fresh->permissions()->pluck('code')->all())->toBe(['system.users.index']);
});

it('returns 403 when creating a role with permissions the author does not hold', function () {
    $outsider = Permission::factory()->create(['code' => 'system.users.delete', 'type' => 'api']);
    $admin = userWithPermissions(['system.roles.create']);

    bearer($admin)->postJson('/api/v1/system/roles', [
        'code' => 'borrowed_authority',
        'name' => '越权角色',
        'permission_ids' => [$outsider->id],
    ])->assertForbidden()->assertJsonPath('code', 403);

    expect(Role::query()->where('code', 'borrowed_authority')->exists())->toBeFalse();
});
