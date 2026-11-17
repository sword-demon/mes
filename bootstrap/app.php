<?php

use App\Core\Exceptions\ApiExceptionRenderer;
use App\Http\Controllers\OpenApiController;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/api-docs', [OpenApiController::class, 'ui'])->name('openapi.ui');
            Route::get('/api-docs/openapi.json', [OpenApiController::class, 'document'])->name('openapi.document');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
        $middleware->redirectGuestsTo(null);
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(
            fn (Throwable $e, Request $request) => $request->is('api/*')
                ? ApiExceptionRenderer::render($e)
                : null,
        );
    })->create();
