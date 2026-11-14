<?php

use App\Modules\Material\Controller\MaterialController;
use App\Modules\Organization\Controller\EmployeeController;
use App\Modules\Organization\Controller\FactoryController;
use App\Modules\Organization\Controller\ProductionLineController;
use App\Modules\Organization\Controller\TeamController;
use App\Modules\Organization\Controller\WorkshopController;
use App\Modules\Product\Controller\ProductController;
use App\Modules\Product\Controller\ProductVersionController;
use App\Modules\System\Controller\AccessTokenController;
use App\Modules\System\Controller\DepartmentController;
use App\Modules\System\Controller\MeController;
use App\Modules\System\Controller\MenuController;
use App\Modules\System\Controller\PermissionController;
use App\Modules\System\Controller\RoleController;
use App\Modules\System\Controller\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/tokens', [AccessTokenController::class, 'store'])
    ->middleware('throttle:login')
    ->name('api.auth.tokens.store');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('/me', [MeController::class, 'show'])->name('api.me');

    Route::delete('/auth/tokens', [AccessTokenController::class, 'destroy'])
        ->name('api.auth.tokens.destroy');

    Route::prefix('system')->name('api.system.')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:system.users.index')->name('users.index');
        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:system.users.create')->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:system.users.update')->name('users.update');
        Route::put('/users/{user}/password', [UserController::class, 'resetPassword'])
            ->middleware('permission:system.users.reset-password')->name('users.password');
        Route::put('/users/{user}/roles', [UserController::class, 'assignRoles'])
            ->middleware('permission:system.users.assign-roles')->name('users.roles');

        Route::get('/roles', [RoleController::class, 'index'])
            ->middleware('permission:system.roles.index')->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])
            ->middleware('permission:system.roles.create')->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:system.roles.update')->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:system.roles.delete')->name('roles.destroy');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:system.permissions.index')->name('permissions.index');

        Route::get('/menus', [MenuController::class, 'index'])
            ->middleware('permission:system.menus.index')->name('menus.index');
        Route::post('/menus', [MenuController::class, 'store'])
            ->middleware('permission:system.menus.create')->name('menus.store');
        Route::put('/menus/{menu}', [MenuController::class, 'update'])
            ->middleware('permission:system.menus.update')->name('menus.update');
        Route::delete('/menus/{menu}', [MenuController::class, 'destroy'])
            ->middleware('permission:system.menus.delete')->name('menus.destroy');

        Route::get('/departments', [DepartmentController::class, 'index'])
            ->middleware('permission:system.departments.index')->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])
            ->middleware('permission:system.departments.create')->name('departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])
            ->middleware('permission:system.departments.update')->name('departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
            ->middleware('permission:system.departments.delete')->name('departments.destroy');
    });

    Route::prefix('basic-data')->name('api.basic-data.')->group(function (): void {
        Route::get('/factories', [FactoryController::class, 'index'])
            ->middleware('permission:basic.factories.index')->name('factories.index');
        Route::post('/factories', [FactoryController::class, 'store'])
            ->middleware('permission:basic.factories.create')->name('factories.store');
        Route::put('/factories/{factory}', [FactoryController::class, 'update'])
            ->middleware('permission:basic.factories.update')->name('factories.update');
        Route::delete('/factories/{factory}', [FactoryController::class, 'destroy'])
            ->middleware('permission:basic.factories.delete')->name('factories.destroy');

        Route::get('/workshops', [WorkshopController::class, 'index'])
            ->middleware('permission:basic.workshops.index')->name('workshops.index');
        Route::post('/workshops', [WorkshopController::class, 'store'])
            ->middleware('permission:basic.workshops.create')->name('workshops.store');
        Route::put('/workshops/{workshop}', [WorkshopController::class, 'update'])
            ->middleware('permission:basic.workshops.update')->name('workshops.update');
        Route::delete('/workshops/{workshop}', [WorkshopController::class, 'destroy'])
            ->middleware('permission:basic.workshops.delete')->name('workshops.destroy');

        Route::get('/production-lines', [ProductionLineController::class, 'index'])
            ->middleware('permission:basic.lines.index')->name('production-lines.index');
        Route::post('/production-lines', [ProductionLineController::class, 'store'])
            ->middleware('permission:basic.lines.create')->name('production-lines.store');
        Route::put('/production-lines/{production_line}', [ProductionLineController::class, 'update'])
            ->middleware('permission:basic.lines.update')->name('production-lines.update');
        Route::delete('/production-lines/{production_line}', [ProductionLineController::class, 'destroy'])
            ->middleware('permission:basic.lines.delete')->name('production-lines.destroy');

        Route::get('/teams', [TeamController::class, 'index'])
            ->middleware('permission:basic.teams.index')->name('teams.index');
        Route::post('/teams', [TeamController::class, 'store'])
            ->middleware('permission:basic.teams.create')->name('teams.store');
        Route::put('/teams/{team}', [TeamController::class, 'update'])
            ->middleware('permission:basic.teams.update')->name('teams.update');
        Route::delete('/teams/{team}', [TeamController::class, 'destroy'])
            ->middleware('permission:basic.teams.delete')->name('teams.destroy');

        Route::get('/employees', [EmployeeController::class, 'index'])
            ->middleware('permission:basic.employees.index')->name('employees.index');
        Route::post('/employees', [EmployeeController::class, 'store'])
            ->middleware('permission:basic.employees.create')->name('employees.store');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])
            ->middleware('permission:basic.employees.update')->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])
            ->middleware('permission:basic.employees.delete')->name('employees.destroy');

        Route::get('/products', [ProductController::class, 'index'])
            ->middleware('permission:basic.products.index')->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware('permission:basic.products.create')->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->middleware('permission:basic.products.update')->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:basic.products.delete')->name('products.destroy');

        Route::get('/materials', [MaterialController::class, 'index'])
            ->middleware('permission:basic.materials.index')->name('materials.index');
        Route::post('/materials', [MaterialController::class, 'store'])
            ->middleware('permission:basic.materials.create')->name('materials.store');
        Route::put('/materials/{material}', [MaterialController::class, 'update'])
            ->middleware('permission:basic.materials.update')->name('materials.update');

        // A version is always reached through its product, so a mismatched
        // pair never resolves.
        Route::scopeBindings()->group(function (): void {
            Route::get('/products/{product}/versions', [ProductVersionController::class, 'index'])
                ->middleware('permission:basic.products.index')->name('products.versions.index');
            Route::post('/products/{product}/versions', [ProductVersionController::class, 'store'])
                ->middleware('permission:basic.products.create')->name('products.versions.store');
            Route::put('/products/{product}/versions/{version}', [ProductVersionController::class, 'update'])
                ->middleware('permission:basic.products.update')->name('products.versions.update');
        });
    });
});
