<?php

use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Database\Seeders\SystemSeeder;

test('the system seeder is idempotent and covers roles, users, permissions and menus', function () {
    $this->seed(SystemSeeder::class);

    $counts = [
        'roles' => Role::query()->count(),
        'users' => User::query()->count(),
        'permissions' => Permission::query()->count(),
        'menus' => Menu::query()->count(),
    ];

    $this->seed(SystemSeeder::class);

    expect(Role::query()->count())->toBe($counts['roles'])
        ->and(User::query()->count())->toBe($counts['users'])
        ->and(Permission::query()->count())->toBe($counts['permissions'])
        ->and(Menu::query()->count())->toBe($counts['menus'])
        ->and($counts['roles'])->toBe(8)
        ->and($counts['users'])->toBe(20);

    expect(Permission::query()->distinct()->pluck('type')->sort()->values()->all())
        ->toBe(['api', 'button', 'data', 'menu', 'page']);

    $admin = User::query()->where('email', 'admin@mes.test')->firstOrFail();

    expect($admin->roles()->value('code'))->toBe(Role::SUPER_ADMIN);

    $this->withToken($admin->createToken('web')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.roles.0.code', Role::SUPER_ADMIN)
        ->assertJsonPath('data.menus.0.name', '首页驾驶舱')
        ->assertJsonCount(10, 'data.menus');
});

test('each seeded role only reaches its own menu roots', function () {
    $this->seed(SystemSeeder::class);

    $equipment = User::query()->where('email', 'equipment1@mes.test')->firstOrFail();

    $names = collect(
        $this->withToken($equipment->createToken('web')->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->json('data.menus'),
    )->pluck('name')->all();

    expect($names)->toBe(['首页驾驶舱', '设备管理']);
});
