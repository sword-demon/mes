<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

test('openapi documentation is enabled by default while developing', function () {
    expect(config('openapi.enabled'))->toBeTrue();
});

test('swagger ui is served locally', function () {
    $response = $this->get('/api-docs');

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/html')
        ->and($response->getContent())->toContain('SwaggerUIBundle', '/api-docs/openapi.json');
});

test('the openapi document covers every api v1 route', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    expect($spec['openapi'])->toStartWith('3.0')
        ->and($spec['info']['title'])->toBe(config('openapi.title'))
        ->and($spec['paths'])->not->toBeEmpty();

    $expected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (RoutingRoute $route): array => collect($route->methods())
            ->reject(fn (string $method): bool => $method === 'HEAD')
            ->map(fn (string $method): array => [
                'path' => '/'.$route->uri(),
                'method' => strtolower($method),
            ])
            ->all())
        ->sortBy(fn (array $operation): string => $operation['path'].' '.$operation['method'])
        ->values()
        ->all();

    $documented = collect($spec['paths'])
        ->flatMap(fn (array $operations, string $path): array => collect(array_keys($operations))
            ->map(fn (string $method): array => ['path' => $path, 'method' => $method])
            ->all())
        ->sortBy(fn (array $operation): string => $operation['path'].' '.$operation['method'])
        ->values()
        ->all();

    expect($expected)->not->toBeEmpty()
        ->and($documented)->toBe($expected);
});

test('the document describes sanctum bearer authentication and permissions', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    expect($spec['components']['securitySchemes']['bearerAuth'])
        ->toMatchArray(['type' => 'http', 'scheme' => 'bearer']);

    expect($spec['paths']['/api/v1/auth/tokens']['post']['security'])->toBe([])
        ->and($spec['paths']['/api/v1/auth/tokens']['delete']['security'])->toBe([['bearerAuth' => []]])
        ->and($spec['paths']['/api/v1/basic-data/boms']['get']['security'])->toBe([['bearerAuth' => []]])
        ->and($spec['paths']['/api/v1/basic-data/boms']['get']['description'])->toContain('basic.boms.index')
        ->and($spec['paths']['/api/v1/equipments/monitor']['get']['description'])
        ->toContain('equipment.monitor.index', 'equipment.history.index');
});

test('every operation documents the unified response envelope', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    expect($spec['components']['schemas']['ApiResponse']['required'])
        ->toContain('code', 'message', 'data', 'timestamp');

    foreach ($spec['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            expect($operation['responses']['200']['content']['application/json']['schema']['$ref'])
                ->toBe('#/components/schemas/ApiResponse', "{$method} {$path} 缺少统一响应契约");
        }
    }
});

test('write operations expose request bodies and the login schema', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    $login = $spec['paths']['/api/v1/auth/tokens']['post']['requestBody']['content']['application/json']['schema'];
    $bom = $spec['paths']['/api/v1/basic-data/boms']['post']['requestBody']['content']['application/json']['schema'];

    expect($login['required'])->toContain('email', 'password')
        ->and($login['properties'])->toHaveKeys(['email', 'password', 'device_name'])
        ->and($bom['type'])->toBe('object');
});

test('response codes and parameters match the implementation', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    $history = $spec['paths']['/api/v1/equipments/{equipment}/history']['get'];

    expect($history['responses'])->toHaveKeys(['200', '401', '403', '404', '422'])
        ->and(collect($history['parameters'])->pluck('name')->all())
        ->toContain('equipment', 'start_at', 'end_at')
        ->and($spec['paths']['/api/v1/me']['get']['responses'])->not->toHaveKey('403')
        ->and($spec['paths']['/api/v1/basic-data/boms']['get']['responses'])->toHaveKey('403')
        ->and($spec['paths']['/api/v1/basic-data/boms/{bom}']['put']['responses'])->toHaveKey('404');
});

test('documentation access is closed when disabled', function () {
    config(['openapi.enabled' => false]);

    $this->get('/api-docs')->assertNotFound();
    $this->getJson('/api-docs/openapi.json')->assertNotFound();
});

test('openapi documentation defaults to off in production', function () {
    $result = Process::path(base_path())
        ->env(['APP_ENV' => 'production'])
        ->run('php artisan tinker --execute="echo config(\'openapi.enabled\') ? \'on\' : \'off\';"');

    expect($result->successful())->toBeTrue()
        ->and(trim($result->output()))->toBe('off');
});
