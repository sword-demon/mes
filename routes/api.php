<?php

use App\Http\Controllers\Api\V1\Auth\AccessTokenController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/tokens', [AccessTokenController::class, 'store'])
    ->middleware('throttle:login')
    ->name('api.auth.tokens.store');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [MeController::class, 'show'])->name('api.me');

    Route::delete('/auth/tokens', [AccessTokenController::class, 'destroy'])
        ->name('api.auth.tokens.destroy');
});
