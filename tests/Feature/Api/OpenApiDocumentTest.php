<?php

use App\Core\OpenApi\OpenApiConfig;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

test('openapi documentation defaults to on only in local and testing', function () {
    expect(OpenApiConfig::enabledByDefault('local'))->toBeTrue()
        ->and(OpenApiConfig::enabledByDefault('testing'))->toBeTrue()
        ->and(OpenApiConfig::enabledByDefault('staging'))->toBeFalse()
        ->and(OpenApiConfig::enabledByDefault('production'))->toBeFalse();
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
                ->toBeIn(
                    ['#/components/schemas/ApiResponse', '#/components/schemas/PaginatedApiResponse'],
                    "{$method} {$path} 缺少统一响应契约",
                );
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
        ->and($spec['paths']['/api/v1/basic-data/boms/{bom}']['put']['responses'])->toHaveKey('404')
        ->and($spec['paths']['/api/v1/equipments/alarms']['get']['responses'])->not->toHaveKey('404');
});

test('documentation access is closed when disabled', function () {
    config(['openapi.enabled' => false]);

    $this->get('/api-docs')->assertNotFound();
    $this->getJson('/api-docs/openapi.json')->assertNotFound();
});

test('major write endpoints document their form fields', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    $bom = $spec['paths']['/api/v1/basic-data/boms']['post']['requestBody']['content']['application/json']['schema'];
    $order = $spec['paths']['/api/v1/production/orders']['post']['requestBody']['content']['application/json']['schema'];

    expect($bom['required'])->toContain('code', 'version', 'product_version_id')
        ->and($bom['properties'])->toHaveKeys(['code', 'version', 'remark', 'status', 'product_version_id'])
        ->and($order['required'])->toContain('code', 'product_id', 'bom_id', 'route_id', 'production_line_id', 'planned_quantity')
        ->and($order['properties'])->toHaveKeys(['code', 'product_id', 'bom_id', 'route_id', 'production_line_id', 'planned_quantity', 'planned_start_at']);
});

test('file upload endpoints use multipart form data', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    $exception = $spec['paths']['/api/v1/production/exceptions']['post']['requestBody'];
    $images = $spec['paths']['/api/v1/quality/inspection-tasks/{task}/images']['post']['requestBody'];

    expect(array_keys($exception['content']))->toBe(['multipart/form-data'])
        ->and($exception['content']['multipart/form-data']['schema']['properties'])->toHaveKey('images')
        ->and(array_keys($images['content']))->toBe(['multipart/form-data'])
        ->and($images['content']['multipart/form-data']['schema']['properties'])->toHaveKey('images');
});

test('state transitions document business validation failures', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    expect($spec['paths']['/api/v1/basic-data/boms/{bom}/submit']['post']['responses'])->toHaveKey('422')
        ->and($spec['paths']['/api/v1/basic-data/boms/{bom}/approve']['post']['responses'])->toHaveKey('422')
        ->and($spec['paths']['/api/v1/production/orders/{order}/release']['post']['responses'])->toHaveKey('422');
});

test('paginated endpoints reference the paginated response schema', function () {
    $spec = $this->getJson('/api-docs/openapi.json')->assertOk()->json();

    expect($spec['paths']['/api/v1/basic-data/boms']['get']['responses']['200']['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/PaginatedApiResponse')
        ->and($spec['paths']['/api/v1/basic-data/boms']['post']['responses']['200']['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/ApiResponse');
});

test('documented query parameters exist in the controller implementation', function () {
    foreach (config('openapi.parameters') as $routeName => $documented) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull("{$routeName} 未注册");

        [$controller, $method] = explode('@', $route->getActionName(), 2);
        $reflection = new ReflectionMethod($controller, $method);
        $lines = file($reflection->getFileName());
        $source = implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));

        foreach ($documented as $parameter) {
            expect($source)->toContain($parameter['name']);
        }
    }
});
