<?php

namespace App\Http\Controllers;

use App\Core\OpenApi\OpenApiDocument;
use App\Core\OpenApi\SwaggerUiPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class OpenApiController extends Controller
{
    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SwaggerUiPage $page,
    ) {}

    public function ui(): Response
    {
        $this->ensureEnabled();

        return new Response($this->page->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
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
}
