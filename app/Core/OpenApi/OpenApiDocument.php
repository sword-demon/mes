<?php

namespace App\Core\OpenApi;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

final class OpenApiDocument
{
    private const API_URI_PREFIX = 'api/v1/';

    public function __construct(
        private readonly RouteDocumenter $operations,
        private readonly OpenApiSchemas $schemas,
    ) {}

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
                $paths['/'.$route->uri()][strtolower($method)] = $this->operations->operation($route, $method, $tag);
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
                'schemas' => $this->schemas->all(),
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

    private function tag(Route $route): string
    {
        return explode('/', $route->uri())[2] ?? 'default';
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
