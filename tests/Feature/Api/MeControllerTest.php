<?php

use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;

it('returns 200 with the roles, permission codes and permitted menu tree', function () {
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

it('returns 200 with every menu for a super admin without explicit permissions', function () {
    $role = Role::factory()->create(['code' => Role::SUPER_ADMIN]);
    Menu::factory()->create(['name' => '系统管理']);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonCount(1, 'data.menus')
        ->assertJsonPath('data.menus.0.name', '系统管理');
});

it('hides a menu whose permission row was deleted', function () {
    $role = Role::factory()->create();
    $granted = Permission::factory()->create(['code' => 'menu.dashboard', 'type' => 'menu']);
    $role->permissions()->attach($granted);

    Menu::factory()->create(['name' => '首页驾驶舱', 'permission_id' => $granted->id]);
    $orphan = Menu::factory()->create(['name' => '孤儿菜单']);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonCount(1, 'data.menus')
        ->assertJsonPath('data.menus.0.name', '首页驾驶舱')
        ->assertJsonMissing(['name' => $orphan->name]);
});

it('returns 200 with empty roles, permissions and menus for a user without roles', function () {
    bearer(User::factory()->create())->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.permissions', [])
        ->assertJsonPath('data.menus', [])
        ->assertJsonPath('data.roles', [])
        ->assertJsonPath('data.data_scope', 'none');
});

it('returns 401 without a token', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
});
