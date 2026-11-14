<?php

use App\Modules\System\Controller\AccessTokenController;
use App\Modules\System\Controller\MeController;
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
    });
});
