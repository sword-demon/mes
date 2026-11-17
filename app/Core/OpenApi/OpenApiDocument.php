<?php

namespace App\Core\OpenApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;

final class OpenApiDocument
{
    private const API_URI_PREFIX = 'api/v1/';

    /** @var array<string, bool> */
    private array $validationCache = [];

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $paths = [];
        $usedTags = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), self::API_URI_PREFIX)) {
                continue;
            }

            $tag = $this->tag($route);
            $usedTags[] = $tag;

            foreach ($this->httpMethods($route) as $method) {
                $paths['/'.$route->uri()][strtolower($method)] = $this->operation($route, $method, $tag);
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('openapi.title'),
                'version' => config('openapi.version'),
                'description' => config('openapi.description'),
            ],
            'servers' => [
                ['url' => '/', 'description' => '当前站点'],
            ],
            'tags' => $this->tags(array_values(array_unique($usedTags))),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Sanctum personal access token',
                        'description' => '调用 POST /api/v1/auth/tokens 获取 token，然后在请求头携带 Authorization: Bearer {token}。',
                    ],
                ],
                'schemas' => $this->schemas(),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function httpMethods(Route $route): array
    {
        return array_values(array_filter(
            $route->methods(),
            fn (string $method): bool => $method !== 'HEAD',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(Route $route, string $method, string $tag): array
    {
        $middleware = $route->gatherMiddleware();
        $protected = $this->isProtected($middleware);
        $permissions = $this->permissions($middleware);
        $parameters = $this->parameters($route);

        $operation = [
            'tags' => [$tag],
            'summary' => $this->summary($route),
            'operationId' => $route->getName() ?? strtolower($method).' '.$route->uri(),
            'description' => $this->description($protected, $permissions),
            'security' => $protected ? [['bearerAuth' => []]] : [],
            'parameters' => $parameters,
            'responses' => $this->responses(
                $protected,
                $permissions !== [],
                $parameters !== [],
                $this->validatesInput($route),
            ),
        ];

        $requestBody = $this->requestBody($route, $method);

        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }

        return $operation;
    }

    private function tag(Route $route): string
    {
        return explode('/', $route->uri())[2] ?? 'default';
    }

    private function summary(Route $route): string
    {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            return Str::headline($route->uri());
        }

        [$controller, $method] = explode('@', $action, 2);
        $resource = Str::beforeLast(class_basename($controller), 'Controller');

        return Str::headline($resource).' · '.Str::headline($method);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function description(bool $protected, array $permissions): string
    {
        $lines = [$protected ? '需要 Bearer Token 认证。' : '公开接口，无需认证。'];

        if ($permissions !== []) {
            $lines[] = '所需权限：'.implode(' 或 ', array_map(
                fn (string $permission): string => '`'.$permission.'`',
                $permissions,
            )).'。';
        }

        return implode("\n\n", $lines);
    }

    /**
     * @param  array<int, string>  $middleware
     */
    private function isProtected(array $middleware): bool
    {
        return collect($middleware)->contains(
            fn (string $item): bool => str_starts_with($item, 'auth:') || str_contains($item, 'Authenticate'),
        );
    }

    /**
     * @param  array<int, string>  $middleware
     * @return array<int, string>
     */
    private function permissions(array $middleware): array
    {
        return collect($middleware)
            ->flatMap(function (string $item): array {
                if (! preg_match('/permission:(.+)$/i', $item, $matches)) {
                    return [];
                }

                return explode('|', $matches[1]);
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parameters(Route $route): array
    {
        $configured = (array) config('openapi.parameters', []);
        $extra = $configured[$route->getName() ?? ''] ?? [];

        return [...$this->pathParameters($route->uri()), ...$extra];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pathParameters(string $uri): array
    {
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);

        return collect($matches[1])
            ->map(function (string $parameter): array {
                $optional = str_ends_with($parameter, '?');

                return [
                    'name' => rtrim($parameter, '?'),
                    'in' => 'path',
                    'required' => ! $optional,
                    'description' => '路径参数',
                    'schema' => ['type' => 'string'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestBody(Route $route, string $method): ?array
    {
        if (! in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return null;
        }

        $schemas = (array) config('openapi.request_bodies', []);
        $name = $route->getName() ?? '';
        $schema = $schemas[$name] ?? [
            'type' => 'object',
            'description' => 'JSON 请求体；字段以对应 FormRequest 的校验规则为准。',
        ];

        return [
            'required' => isset($schemas[$name]),
            'content' => [
                'application/json' => ['schema' => $schema],
            ],
        ];
    }

    private function validatesInput(Route $route): bool
    {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            return false;
        }

        if (array_key_exists($action, $this->validationCache)) {
            return $this->validationCache[$action];
        }

        [$controller, $method] = explode('@', $action, 2);

        if (! method_exists($controller, $method)) {
            return $this->validationCache[$action] = false;
        }

        $reflection = new ReflectionMethod($controller, $method);

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType
                && ! $type->isBuiltin()
                && is_subclass_of($type->getName(), FormRequest::class)) {
                return $this->validationCache[$action] = true;
            }
        }

        $file = $reflection->getFileName();
        $lines = $file === false ? false : file($file);

        if ($lines === false) {
            return $this->validationCache[$action] = false;
        }

        $body = implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));

        return $this->validationCache[$action] = str_contains($body, 'validate(');
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function responses(
        bool $protected,
        bool $hasPermissions,
        bool $hasPathParameters,
        bool $validatesInput,
    ): array {
        $responses = [
            '200' => [
                'description' => '成功',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ApiResponse'],
                    ],
                ],
            ],
        ];

        if ($protected) {
            $responses['401'] = $this->errorResponse('未认证或 Token 失效');
        }

        if ($hasPermissions) {
            $responses['403'] = $this->errorResponse('缺少所需权限');
        }

        if ($hasPathParameters) {
            $responses['404'] = $this->errorResponse('资源不存在');
        }

        if ($validatesInput) {
            $responses['422'] = $this->errorResponse('请求参数校验失败');
        }

        return $responses;
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(string $description): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/ApiResponse'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function schemas(): array
    {
        return [
            'ApiResponse' => [
                'type' => 'object',
                'required' => ['code', 'message', 'data', 'timestamp'],
                'properties' => [
                    'code' => [
                        'type' => 'integer',
                        'description' => '业务状态码；成功为 200，失败与 HTTP 状态码保持一致',
                        'example' => 200,
                    ],
                    'message' => [
                        'type' => 'string',
                        'description' => '结果说明；成功时为 success',
                        'example' => 'success',
                    ],
                    'data' => [
                        'nullable' => true,
                        'description' => '业务数据；分页接口为 PaginatedData，校验失败时为 errors 详情，无数据时为 null',
                    ],
                    'timestamp' => [
                        'type' => 'integer',
                        'description' => '服务器 Unix 时间戳（秒）',
                        'example' => 1760000000,
                    ],
                ],
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'required' => ['current_page', 'per_page', 'total', 'last_page'],
                'properties' => [
                    'current_page' => ['type' => 'integer', 'example' => 1],
                    'per_page' => ['type' => 'integer', 'example' => 15],
                    'total' => ['type' => 'integer', 'example' => 42],
                    'last_page' => ['type' => 'integer', 'example' => 3],
                ],
            ],
            'PaginatedData' => [
                'type' => 'object',
                'required' => ['items', 'meta'],
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'description' => '当前页数据',
                        'items' => ['type' => 'object'],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $used
     * @return array<int, array{name: string, description: string}>
     */
    private function tags(array $used): array
    {
        $configured = (array) config('openapi.tags', []);

        $tags = collect($configured)
            ->filter(fn (string $description, string $name): bool => in_array($name, $used, true))
            ->map(fn (string $description, string $name): array => ['name' => $name, 'description' => $description])
            ->values()
            ->all();

        $extra = collect($used)
            ->diff(array_keys($configured))
            ->map(fn (string $name): array => ['name' => $name, 'description' => Str::headline($name)])
            ->values()
            ->all();

        return [...$tags, ...$extra];
    }
}
