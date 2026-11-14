<?php

use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;

it('returns 401 when listing users without a token', function () {
    $this->getJson('/api/v1/system/users')
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
});

it('returns 403 when listing users without the permission', function () {
    bearer(userWithPermissions([]))->getJson('/api/v1/system/users')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('returns 200 when listing users with the permission', function () {
    $admin = userWithPermissions(['system.users.index']);
    User::factory()->create();

    bearer($admin)->getJson('/api/v1/system/users')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2);
});

it('filters the user list by keyword', function () {
    $admin = userWithPermissions(['system.users.index']);
    User::factory()->create(['name' => '王五']);
    User::factory()->create(['name' => '赵六']);

    bearer($admin)->getJson('/api/v1/system/users?'.http_build_query(['keyword' => '王五']))
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.name', '王五');
});

it('creates a user with roles and stores a hashed password', function () {
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

it('returns 422 when creating a user with a duplicate email', function () {
    $admin = userWithPermissions(['system.users.create']);
    $existing = User::factory()->create();

    bearer($admin)->postJson('/api/v1/system/users', [
        'name' => '重复',
        'email' => $existing->email,
        'password' => 'secret-password',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 422);
});

it('returns 422 when creating a user without a password', function () {
    $admin = userWithPermissions(['system.users.create']);

    bearer($admin)->postJson('/api/v1/system/users', ['name' => '无密码', 'email' => 'nopass@mes.test'])
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.password.0', 'The password field is required.');
});

it('returns 422 when creating a user with an unknown role', function () {
    $admin = userWithPermissions(['system.users.create']);

    $response = bearer($admin)->postJson('/api/v1/system/users', [
        'name' => '角色不存在',
        'email' => 'badrole@mes.test',
        'password' => 'secret-password',
        'role_ids' => [9999],
    ])->assertUnprocessable();

    expect($response->json('data.errors')['role_ids.0'][0])
        ->toBe('The selected role_ids.0 is invalid.');
});

it('returns 403 when disabling the current account', function () {
    $admin = userWithPermissions(['system.users.update']);

    bearer($admin)->putJson("/api/v1/system/users/{$admin->id}", ['status' => false])
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    expect($admin->fresh()->status)->toBeTrue();
});

it('updates a user, resets the password, revokes tokens and reassigns roles', function () {
    $admin = userWithPermissions([
        'system.users.update',
        'system.users.reset-password',
        'system.users.assign-roles',
    ]);
    $user = User::factory()->create();
    $first = Role::factory()->create();
    $second = Role::factory()->create();
    $user->createToken('old-device');

    bearer($admin)->putJson("/api/v1/system/users/{$user->id}", [
        'name' => '李四',
        'email' => $user->email,
        'phone' => '13900000000',
        'status' => false,
    ])->assertOk()
        ->assertJsonPath('data.name', '李四')
        ->assertJsonPath('data.status', false);

    bearer($admin)->putJson("/api/v1/system/users/{$user->id}/password", [
        'password' => 'replacement-password',
    ])->assertOk();

    bearer($admin)->putJson("/api/v1/system/users/{$user->id}/roles", [
        'role_ids' => [$first->id, $second->id],
    ])->assertOk()->assertJsonCount(2, 'data.roles');

    expect(Hash::check('replacement-password', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);
});
