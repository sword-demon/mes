<?php

use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A user holding a role that grants exactly these permission codes.
 *
 * @param  array<int, string>  $permissionCodes
 */
function userWithPermissions(array $permissionCodes, string $roleCode = 'tester'): User
{
    $role = Role::factory()->create(['code' => $roleCode]);

    foreach ($permissionCodes as $code) {
        $role->permissions()->attach(Permission::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'type' => 'api'],
        ));
    }

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

function superAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::factory()->create(['code' => Role::SUPER_ADMIN]));

    return $user;
}

/**
 * @return array{0: Warehouse, 1: StorageLocation}
 */
function inventoryLocation(string $warehouseCode = 'WH01', string $locationCode = 'A-01'): array
{
    $warehouse = Warehouse::query()->firstOrCreate(
        ['code' => $warehouseCode],
        ['name' => '仓库 '.$warehouseCode, 'status' => true],
    );
    $location = StorageLocation::query()->firstOrCreate(
        ['warehouse_id' => $warehouse->id, 'code' => $locationCode],
        ['name' => '库位 '.$locationCode, 'status' => true],
    );

    return [$warehouse, $location];
}

function bearer(User $user): TestCase
{
    // The auth guard caches the resolved user across test requests; switching
    // actors inside one test must start from a clean guard.
    app('auth')->forgetGuards();

    return test()->withToken($user->createToken('web')->plainTextToken);
}
