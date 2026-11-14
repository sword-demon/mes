<?php

use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;

it('returns the full menu tree to a user holding the menu list permission', function () {
    $actor = userWithPermissions(['system.menus.index']);
    $parent = Menu::factory()->create(['name' => '生产管理', 'sort' => 2]);
    Menu::factory()->create(['name' => '生产订单', 'parent_id' => $parent->id, 'sort' => 1]);
    Menu::factory()->create(['name' => '首页驾驶舱', 'sort' => 1]);

    bearer($actor)->getJson('/api/v1/system/menus')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', '首页驾驶舱')
        ->assertJsonPath('data.1.children.0.name', '生产订单');
});

it('returns 403 when listing menus without the menu list permission', function () {
    $actor = userWithPermissions([]);

    bearer($actor)->getJson('/api/v1/system/menus')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('creates a child menu bound to a permission', function () {
    $actor = userWithPermissions(['system.menus.create']);
    $parent = Menu::factory()->create(['name' => '系统管理']);
    $permission = Permission::factory()->create(['code' => 'menu.system.logs', 'type' => 'menu']);

    bearer($actor)->postJson('/api/v1/system/menus', [
        'parent_id' => $parent->id,
        'permission_id' => $permission->id,
        'name' => '操作日志',
        'path' => '/system/logs',
        'sort' => 3,
    ])->assertOk()
        ->assertJsonPath('data.name', '操作日志')
        ->assertJsonPath('data.parent_id', $parent->id)
        ->assertJsonPath('data.permission_id', $permission->id);

    expect(Menu::query()->where('path', '/system/logs')->exists())->toBeTrue();
});

it('returns 422 when the parent menu does not exist', function () {
    $actor = userWithPermissions(['system.menus.create']);

    bearer($actor)->postJson('/api/v1/system/menus', [
        'parent_id' => 999, 'name' => '孤儿',
    ])->assertUnprocessable()->assertJsonPath('code', 422);
});

it('updates a menu name and order', function () {
    $actor = userWithPermissions(['system.menus.update']);
    $menu = Menu::factory()->create(['name' => '旧菜单', 'sort' => 1]);

    bearer($actor)->putJson("/api/v1/system/menus/{$menu->id}", ['name' => '新菜单', 'sort' => 9])
        ->assertOk()
        ->assertJsonPath('data.name', '新菜单')
        ->assertJsonPath('data.sort', 9);
});

it('deletes a leaf menu', function () {
    $actor = userWithPermissions(['system.menus.delete']);
    $menu = Menu::factory()->create();

    bearer($actor)->deleteJson("/api/v1/system/menus/{$menu->id}")->assertOk();

    expect(Menu::query()->whereKey($menu->id)->exists())->toBeFalse();
});

it('returns 403 when deleting a menu that still has children', function () {
    $actor = userWithPermissions(['system.menus.delete']);
    $parent = Menu::factory()->create();
    Menu::factory()->create(['parent_id' => $parent->id]);

    bearer($actor)->deleteJson("/api/v1/system/menus/{$parent->id}")
        ->assertForbidden()->assertJsonPath('code', 403);

    expect(Menu::query()->whereKey($parent->id)->exists())->toBeTrue();
});
