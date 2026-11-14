<?php

use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;

it('returns 403 when a non super admin assigns the super admin role', function () {
    $actor = userWithPermissions(['system.users.assign-roles']);
    $target = User::factory()->create();
    $superAdminRole = Role::factory()->create(['code' => Role::SUPER_ADMIN]);

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/roles", [
        'role_ids' => [$superAdminRole->id],
    ])->assertForbidden()->assertJsonPath('code', 403);

    expect($target->roles()->count())->toBe(0);
});

it('returns 403 when a non super admin creates a user with the super admin role', function () {
    $actor = userWithPermissions(['system.users.create']);
    $superAdminRole = Role::factory()->create(['code' => Role::SUPER_ADMIN]);

    bearer($actor)->postJson('/api/v1/system/users', [
        'name' => '提权账号',
        'email' => 'escalated@mes.test',
        'password' => 'secret-password',
        'role_ids' => [$superAdminRole->id],
    ])->assertForbidden()->assertJsonPath('code', 403);

    expect(User::query()->where('email', 'escalated@mes.test')->exists())->toBeFalse();
});

it('returns 403 when creating a role with the reserved super admin code', function () {
    $actor = userWithPermissions(['system.roles.create']);

    bearer($actor)->postJson('/api/v1/system/roles', [
        'code' => Role::SUPER_ADMIN,
        'name' => '伪造超管',
    ])->assertForbidden()->assertJsonPath('code', 403);

    expect(Role::query()->where('code', Role::SUPER_ADMIN)->exists())->toBeFalse();
});

it('returns 403 when renaming an ordinary role to the reserved super admin code', function () {
    $actor = userWithPermissions(['system.roles.update']);
    $role = Role::factory()->create(['code' => 'ordinary']);

    bearer($actor)->putJson("/api/v1/system/roles/{$role->id}", [
        'code' => Role::SUPER_ADMIN,
    ])->assertForbidden()->assertJsonPath('code', 403);

    expect($role->fresh()->code)->toBe('ordinary');
});

it('returns 403 when a non super admin resets a super admin password', function () {
    $actor = userWithPermissions(['system.users.reset-password']);
    $target = superAdmin();
    $before = $target->password;

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/password", [
        'password' => 'hijacked-password',
    ])->assertForbidden()->assertJsonPath('code', 403);

    expect($target->fresh()->password)->toBe($before);
});

it('returns 403 when a non super admin disables a super admin', function () {
    $actor = userWithPermissions(['system.users.update']);
    $target = superAdmin();

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}", ['status' => false])
        ->assertForbidden()->assertJsonPath('code', 403);

    expect($target->fresh()->status)->toBeTrue();
});

it('returns 403 when a non super admin replaces a super admin roles', function () {
    $actor = userWithPermissions(['system.users.assign-roles']);
    $target = superAdmin();
    $role = Role::factory()->create();

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/roles", [
        'role_ids' => [$role->id],
    ])->assertForbidden()->assertJsonPath('code', 403);

    expect($target->roles()->where('code', Role::SUPER_ADMIN)->exists())->toBeTrue();
});

it('lets a super admin assign the super admin role', function () {
    $actor = superAdmin();
    $target = User::factory()->create();
    $superAdminRole = $actor->roles()->firstOrFail();

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/roles", [
        'role_ids' => [$superAdminRole->id],
    ])->assertOk()->assertJsonPath('data.roles.0.code', Role::SUPER_ADMIN);
});
