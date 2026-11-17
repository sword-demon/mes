<?php

namespace App\Core\OpenApi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionNamedType;

final class RouteDocumenter
{
    /** @var array<string, string|null> */
    private array $sourceCache = [];

    /**
     * @return array<string, mixed>
     */
    public function operation(Route $route, string $method, string $tag): array
    {
        $middleware = $route->gatherMiddleware();
        $protected = $this->isProtected($middleware);
        $permissions = $this->permissions($middleware);
        $pathParameters = $this->pathParameters($route->uri());
        $parameters = [...$pathParameters, ...$this->configuredParameters($route)];

        $operation = [
            'tags' => [$tag],
            'summary' => $this->summary($route),
            'operationId' => $route->getName() ?? strtolower($method).' '.$route->uri(),
            'description' => $this->description($protected, $permissions),
            'security' => $protected ? [['bearerAuth' => []]] : [],
            'parameters' => $parameters,
            'responses' => $this->responses(
                $route,
                $method,
                $protected,
                $permissions !== [],
                $pathParameters !== [],
            ),
        ];

        $requestBody = $this->requestBody($route, $method);

        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }

        return $operation;
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
     * @return array<int, array<string, mixed>>
     */
    private function configuredParameters(Route $route): array
    {
        $configured = (array) config('openapi.parameters', []);

        return $configured[$route->getName() ?? ''] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestBody(Route $route, string $method): ?array
    {
        if (! $this->canHaveRequestBody($method)) {
            return null;
        }

        $schemas = (array) config('openapi.request_bodies', []);
        $name = $route->getName() ?? '';
        $configured = $schemas[$name] ?? null;
        $uploads = $this->uploadFields($route);

        if ($uploads !== []) {
            $schema = [
                'type' => 'object',
                'properties' => array_merge(
                    $configured['properties'] ?? [],
                    collect($uploads)->mapWithKeys(fn (string $field): array => [
                        $field => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'format' => 'binary'],
                            'description' => '上传文件',
                        ],
                    ])->all(),
                ),
            ];

            if (isset($configured['required'])) {
                $schema['required'] = $configured['required'];
            }

            return [
                'required' => true,
                'content' => ['multipart/form-data' => ['schema' => $schema]],
            ];
        }

        return [
            'required' => $configured !== null,
            'content' => [
                'application/json' => [
                    'schema' => $configured ?? [
                        'type' => 'object',
                        'description' => 'JSON 请求体；字段以对应 FormRequest 的校验规则为准。',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function uploadFields(Route $route): array
    {
        $source = $this->methodSource($route);

        if ($source === null || preg_match_all("/->file\\(\\s*['\"]([^'\"]+)['\"]/", $source, $matches) === 0) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    private function validatesInput(Route $route): bool
    {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            return false;
        }

        [$controller, $method] = explode('@', $action, 2);

        if (! method_exists($controller, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($controller, $method);

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType
                && ! $type->isBuiltin()
                && is_subclass_of($type->getName(), FormRequest::class)) {
                return true;
            }
        }

        $source = $this->methodSource($route);

        return $source !== null && str_contains($source, 'validate(');
    }

    private function usesPagination(Route $route): bool
    {
        $source = $this->methodSource($route);

        return $source !== null
            && preg_match('/ApiResponse::paginated\\(|->paginated\\(/', $source) === 1;
    }

    private function methodSource(Route $route): ?string
    {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            return null;
        }

        if (array_key_exists($action, $this->sourceCache)) {
            return $this->sourceCache[$action];
        }

        [$controller, $method] = explode('@', $action, 2);

        if (! method_exists($controller, $method)) {
            return $this->sourceCache[$action] = null;
        }

        $reflection = new ReflectionMethod($controller, $method);
        $file = $reflection->getFileName();
        $lines = $file === false ? false : file($file);

        if ($lines === false) {
            return $this->sourceCache[$action] = null;
        }

        return $this->sourceCache[$action] = implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private function responses(
        Route $route,
        string $method,
        bool $protected,
        bool $hasPermissions,
        bool $hasPathParameters,
    ): array {
        $responses = [
            '200' => $this->jsonResponse(
                '成功',
                $this->usesPagination($route) ? 'PaginatedApiResponse' : 'ApiResponse',
            ),
        ];

        if ($protected) {
            $responses['401'] = $this->jsonResponse('未认证或 Token 失效');
        }

        if ($hasPermissions) {
            $responses['403'] = $this->jsonResponse('缺少所需权限');
        }

        if ($hasPathParameters) {
            $responses['404'] = $this->jsonResponse('资源不存在');
        }

        if ($this->isWriteMethod($method) || $this->validatesInput($route)) {
            $responses['422'] = $this->jsonResponse('请求参数校验失败');
        }

        return $responses;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(string $description, string $schema = 'ApiResponse'): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/'.$schema],
                ],
            ],
        ];
    }

    private function canHaveRequestBody(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'PATCH'], true);
    }

    private function isWriteMethod(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
