<?php

use App\Modules\System\Model\Department;
use App\Modules\System\Model\Menu;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

test('the access control seeder creates rbac metadata without demo data', function () {
    $this->seed(AccessControlSeeder::class);

    expect(Role::query()->where('code', Role::SUPER_ADMIN)->exists())->toBeTrue()
        ->and(Menu::query()->exists())->toBeTrue()
        ->and(Permission::query()->exists())->toBeTrue()
        ->and(User::query()->exists())->toBeFalse()
        ->and(Department::query()->exists())->toBeFalse();
});

test('the create admin command seeds access control and creates a super admin', function () {
    $exitCode = Artisan::call('mes:create-admin', [
        '--name' => 'Root',
        '--email' => 'root@example.com',
        '--password' => 'Str0ng!Password#2026',
    ]);
    $user = User::query()->where('email', 'root@example.com')->firstOrFail();

    expect($exitCode)->toBe(0)
        ->and($user->name)->toBe('Root')
        ->and($user->roles()->where('code', Role::SUPER_ADMIN)->exists())->toBeTrue()
        ->and(Menu::query()->exists())->toBeTrue()
        ->and(Permission::query()->exists())->toBeTrue();

    $token = $this->postJson('/api/v1/auth/tokens', [
        'email' => 'root@example.com',
        'password' => 'Str0ng!Password#2026',
    ])->assertOk()->json('data.token');

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'all')
        ->assertJsonCount(10, 'data.menus');
});

test('the create admin command rejects a weak password', function () {
    $exitCode = Artisan::call('mes:create-admin', [
        '--email' => 'weak@example.com',
        '--password' => 'short',
    ]);

    expect($exitCode)->not->toBe(0)
        ->and(User::query()->where('email', 'weak@example.com')->exists())->toBeFalse();
});

test('the create admin command refuses an existing email', function () {
    User::factory()->create(['email' => 'root@example.com']);

    $exitCode = Artisan::call('mes:create-admin', [
        '--email' => 'root@example.com',
        '--password' => 'Str0ng!Password#2026',
    ]);

    expect($exitCode)->not->toBe(0)
        ->and(User::query()->where('email', 'root@example.com')->count())->toBe(1)
        ->and(Menu::query()->exists())->toBeTrue();
});

test('the create admin command generates a password when none is supplied', function () {
    $exitCode = Artisan::call('mes:create-admin', [
        '--email' => 'generated@example.com',
    ]);
    $output = Artisan::output();
    $user = User::query()->where('email', 'generated@example.com')->firstOrFail();

    preg_match('/初始密码：(\S+)/u', $output, $matches);

    expect($exitCode)->toBe(0)
        ->and($matches[1] ?? '')->not->toBe('')
        ->and(Hash::check($matches[1], $user->password))->toBeTrue();
});
