<?php

use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Tests\TestCase;

/**
 * @param  array<int, string>  $permissionCodes
 */
function userWithPermissions(array $permissionCodes, string $roleCode = 'tester'): User
{
    $role = Role::factory()->create(['code' => $roleCode]);

    foreach ($permissionCodes as $code) {
        $role->permissions()->attach(Permission::factory()->create([
            'code' => $code,
            'type' => 'api',
        ]));
    }

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function bearer(User $user): TestCase
{
    return test()->withToken($user->createToken('web')->plainTextToken);
}

test('/me returns roles, permission codes and the permitted menu tree', function () {
    $role = Role::factory()->create(['code' => 'workshop_director', 'name' => '车间主任']);
    $rootPermission = Permission::factory()->create(['code' => 'menu.production', 'type' => 'menu']);
    $childPermission = Permission::factory()->create(['code' => 'menu.production.orders', 'type' => 'menu']);

    $production = Menu::factory()->create([
        'name' => '生产管理',
        'path' => '/production',
        'permission_id' => $rootPermission->id,
        'sort' => 1,
    ]);
    Menu::factory()->create([
        'parent_id' => $production->id,
        'name' => '生产订单',
        'path' => '/production/orders',
        'permission_id' => $childPermission->id,
    ]);
    $hidden = Menu::factory()->create([
        'name' => '系统管理',
        'path' => '/system',
        'permission_id' => Permission::factory()->create(['code' => 'menu.system', 'type' => 'menu'])->id,
    ]);

    $role->permissions()->attach([$rootPermission->id, $childPermission->id]);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles.0.code', 'workshop_director')
        ->assertJsonPath('data.permissions', ['menu.production', 'menu.production.orders'])
        ->assertJsonCount(1, 'data.menus')
        ->assertJsonPath('data.menus.0.name', '生产管理')
        ->assertJsonPath('data.menus.0.children.0.name', '生产订单')
        ->assertJsonMissing(['name' => $hidden->name]);
});

test('a super admin sees every menu without explicit permissions', function () {
    $role = Role::factory()->create(['code' => Role::SUPER_ADMIN]);
    Menu::factory()->create(['name' => '系统管理']);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonCount(1, 'data.menus')
        ->assertJsonPath('data.menus.0.name', '系统管理');
});

test('protected endpoints reject users without the matching permission', function () {
    $outsider = userWithPermissions([]);

    bearer($outsider)->getJson('/api/v1/system/users')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

test('protected endpoints allow users holding the matching permission', function () {
    $admin = userWithPermissions(['system.users.index']);
    User::factory()->create();

    bearer($admin)->getJson('/api/v1/system/users')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2);
});

test('disabling a role revokes the permissions it granted', function () {
    $user = userWithPermissions(['system.users.index']);
    $role = $user->roles()->firstOrFail();

    bearer($user)->getJson('/api/v1/system/users')->assertOk();

    $role->update(['status' => false]);
    app('auth')->forgetGuards();

    bearer($user)->getJson('/api/v1/system/users')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

test('a disabled user cannot log in', function () {
    $user = User::factory()->create(['password' => 'secret-password']);
    $user->update(['status' => false]);

    $this->postJson('/api/v1/auth/tokens', [
        'email' => $user->email,
        'password' => 'secret-password',
    ])->assertUnprocessable();

    expect($user->tokens()->count())->toBe(0);
});

test('an already issued token stops working once the user is disabled', function () {
    $user = User::factory()->create();
    $token = $user->createToken('web')->plainTextToken;
    $user->update(['status' => false]);

    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

test('an administrator can create a user and assign roles', function () {
    $admin = userWithPermissions(['system.users.create']);
    $role = Role::factory()->create();

    bearer($admin)->postJson('/api/v1/system/users', [
        'name' => '张三',
        'email' => 'zhangsan@mes.test',
        'phone' => '13800000000',
        'password' => 'secret-password',
        'role_ids' => [$role->id],
    ])->assertOk()
        ->assertJsonPath('data.email', 'zhangsan@mes.test')
        ->assertJsonPath('data.roles.0.id', $role->id);

    $created = User::query()->where('email', 'zhangsan@mes.test')->firstOrFail();

    expect($created->roles()->pluck('sys_roles.id')->all())->toBe([$role->id])
        ->and(Hash::check('secret-password', $created->password))->toBeTrue();
});

test('duplicate user emails are rejected', function () {
    $admin = userWithPermissions(['system.users.create']);
    $existing = User::factory()->create();

    bearer($admin)->postJson('/api/v1/system/users', [
        'name' => '重复',
        'email' => $existing->email,
        'password' => 'secret-password',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 422);
});

test('an administrator can edit, disable, reset a password and reassign roles', function () {
    $admin = userWithPermissions([
        'system.users.update',
        'system.users.reset-password',
        'system.users.assign-roles',
    ]);
    $user = User::factory()->create();
    $first = Role::factory()->create();
    $second = Role::factory()->create();

    bearer($admin)->putJson("/api/v1/system/users/{$user->id}", [
        'name' => '李四',
        'email' => $user->email,
        'phone' => '13900000000',
        'status' => false,
    ])->assertOk()
        ->assertJsonPath('data.name', '李四')
        ->assertJsonPath('data.status', false);

    bearer($admin)->putJson("/api/v1/system/users/{$user->id}/password", [
        'password' => 'brand-new-password',
    ])->assertOk();

    bearer($admin)->putJson("/api/v1/system/users/{$user->id}/roles", [
        'role_ids' => [$first->id, $second->id],
    ])->assertOk()->assertJsonCount(2, 'data.roles');

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

test('an administrator can list and filter users', function () {
    $admin = userWithPermissions(['system.users.index']);
    User::factory()->create(['name' => '王五']);
    User::factory()->create(['name' => '赵六']);

    bearer($admin)->getJson('/api/v1/system/users?'.http_build_query(['keyword' => '王五']))
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.name', '王五');
});

test('an administrator can create a role with permissions and delete an unused role', function () {
    $admin = userWithPermissions(['system.roles.create', 'system.roles.delete']);
    $permission = Permission::factory()->create(['code' => 'system.users.index', 'type' => 'api']);

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

test('a role in use cannot be deleted', function () {
    $admin = userWithPermissions(['system.roles.delete']);
    $role = Role::factory()->create();
    User::factory()->create()->roles()->attach($role);

    bearer($admin)->deleteJson("/api/v1/system/roles/{$role->id}")
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

test('permission list covers all five permission types', function () {
    $admin = userWithPermissions(['system.permissions.index']);

    foreach (['menu', 'page', 'button', 'api', 'data'] as $type) {
        Permission::factory()->create(['code' => "demo.{$type}", 'type' => $type]);
    }

    $types = bearer($admin)->getJson('/api/v1/system/permissions')
        ->assertOk()
        ->json('data');

    expect(array_column($types, 'type'))->toContain('menu', 'page', 'button', 'api', 'data');
});
