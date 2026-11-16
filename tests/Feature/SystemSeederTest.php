<?php

use App\Modules\System\Model\Department;
use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Database\Seeders\SystemSeeder;
use Illuminate\Support\Facades\Artisan;

test('the system seeder is idempotent and covers roles, users, permissions and menus', function () {
    $this->seed(SystemSeeder::class);

    $counts = [
        'roles' => Role::query()->count(),
        'users' => User::query()->count(),
        'permissions' => Permission::query()->count(),
        'menus' => Menu::query()->count(),
        'departments' => Department::query()->count(),
    ];

    $this->seed(SystemSeeder::class);

    expect(Role::query()->count())->toBe($counts['roles'])
        ->and(User::query()->count())->toBe($counts['users'])
        ->and(Permission::query()->count())->toBe($counts['permissions'])
        ->and(Menu::query()->count())->toBe($counts['menus'])
        ->and(Department::query()->count())->toBe($counts['departments'])
        ->and($counts['roles'])->toBe(10)
        ->and($counts['users'])->toBe(22)
        ->and($counts['departments'])->toBe(7);

    expect(Permission::query()->distinct()->pluck('type')->sort()->values()->all())
        ->toBe(['api', 'button', 'data', 'menu', 'page']);

    $admin = User::query()->where('email', 'admin@mes.test')->firstOrFail();

    expect($admin->roles()->value('code'))->toBe(Role::SUPER_ADMIN);

    $this->withToken($admin->createToken('web')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.roles.0.code', Role::SUPER_ADMIN)
        ->assertJsonPath('data.data_scope', 'all')
        ->assertJsonPath('data.menus.0.name', '首页驾驶舱')
        ->assertJsonPath('data.menus.0.children.0.name', '生产数据总览')
        ->assertJsonCount(10, 'data.menus');

    expect($admin->department()->value('name'))->toBe('演示工厂')
        ->and($admin->username)->toBe('admin');
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

    expect(collect($this->withToken($equipment->createToken('web')->plainTextToken)
        ->getJson('/api/v1/me')
        ->json('data.permissions'))->all())->toContain('data.scope.factory');
});

test('the workshop director is scoped to its own production line', function () {
    $this->seed(SystemSeeder::class);

    $director = User::query()->where('email', 'workshop@mes.test')->firstOrFail();

    $this->withToken($director->createToken('web')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'line');
});

test('the equipment manager can monitor equipment and read its history', function () {
    $this->seed(SystemSeeder::class);

    $manager = User::query()->where('email', 'equipment1@mes.test')->firstOrFail();
    $permissions = collect($this->withToken($manager->createToken('web')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->json('data.permissions'))->all();

    expect($permissions)
        ->toContain('equipment.monitor.index')
        ->toContain('equipment.history.index')
        ->toContain('equipment.alarms.index')
        ->toContain('equipment.alarms.confirm')
        ->toContain('equipment.alarms.process')
        ->toContain('equipment.alarms.close');
});

test('the process engineer can read and edit processes and routes', function () {
    $this->seed(SystemSeeder::class);

    $engineer = User::query()->where('email', 'process@mes.test')->firstOrFail();
    $permissions = collect($this->withToken($engineer->createToken('web')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->json('data.permissions'))->all();

    expect($permissions)
        ->toContain('basic.processes.index')
        ->toContain('basic.processes.create')
        ->toContain('basic.processes.update')
        ->toContain('process.routes.index')
        ->toContain('process.routes.create')
        ->toContain('process.routes.update')
        ->toContain('process.routes.submit')
        ->not->toContain('process.routes.audit');
});

test('the seeder refuses to run outside local and testing without SEED_PASSWORD', function () {
    config(['app.seed_password' => null]);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => Artisan::call('db:seed', ['--class' => SystemSeeder::class, '--force' => true]))
        ->toThrow(RuntimeException::class, 'SEED_PASSWORD');

    expect(User::query()->count())->toBe(0);
});
