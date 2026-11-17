<?php

namespace App\Http\Controllers;

use App\Core\OpenApi\OpenApiDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class OpenApiController extends Controller
{
    public function __construct(private readonly OpenApiDocument $document) {}

    public function ui(): Response
    {
        $this->ensureEnabled();

        return new Response($this->html(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function document(): JsonResponse
    {
        $this->ensureEnabled();

        return response()->json($this->document->build());
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('openapi.enabled'), 404);
    }

    private function html(): string
    {
        $title = e(config('openapi.title'));
        $documentUrl = json_encode(
            route('openapi.document', absolute: false),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $documentHref = e(route('openapi.document', absolute: false));

        return <<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        body { margin: 0; background: #fafafa; }
        .swagger-ui .topbar { display: none; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<p id="swagger-fallback" style="display:none;padding:24px;font-family:system-ui,sans-serif">
    Swagger UI 资源加载失败。可直接访问 <a href="{$documentHref}">OpenAPI JSON</a>，或检查网络后刷新。
</p>
<script>
    function showOpenApiFallback() {
        document.getElementById('swagger-ui').style.display = 'none';
        document.getElementById('swagger-fallback').style.display = 'block';
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js" onerror="showOpenApiFallback()"></script>
<script>

    if (typeof SwaggerUIBundle === 'undefined') {
        showOpenApiFallback();
    } else {
        SwaggerUIBundle({
            url: {$documentUrl},
            dom_id: '#swagger-ui',
            deepLinking: true,
            persistAuthorization: true,
        });
    }
</script>
</body>
</html>
HTML;
    }
}
