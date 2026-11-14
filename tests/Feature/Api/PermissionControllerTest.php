<?php

use App\Modules\System\Model\Permission;

it('returns permissions covering all five permission types', function () {
    $admin = userWithPermissions(['system.permissions.index']);

    foreach (['menu', 'page', 'button', 'api', 'data'] as $type) {
        Permission::factory()->create(['code' => "demo.{$type}", 'type' => $type]);
    }

    $types = bearer($admin)->getJson('/api/v1/system/permissions')
        ->assertOk()
        ->json('data');

    expect(array_column($types, 'type'))->toContain('menu', 'page', 'button', 'api', 'data');
});

it('returns 403 without the permission list permission', function () {
    bearer(userWithPermissions([]))->getJson('/api/v1/system/permissions')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});
