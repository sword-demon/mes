<?php

namespace App\Core\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        if ($e instanceof ApiException) {
            return ApiResponse::error($e->getCode(), $e->getMessage(), $e->data, $e->httpStatus());
        }

        if ($e instanceof ValidationException) {
            return ApiResponse::error(422, $e->getMessage(), ['errors' => $e->errors()], 422);
        }

        if ($e instanceof AuthenticationException) {
            return ApiResponse::error(401, $e->getMessage() ?: 'Unauthenticated.', null, 401);
        }

        if ($e instanceof AuthorizationException) {
            return ApiResponse::error(403, $e->getMessage() ?: 'Forbidden.', null, 403);
        }

        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        $message = match (true) {
            $e instanceof HttpExceptionInterface => $e->getMessage() ?: (Response::$statusTexts[$status] ?? 'Error'),
            config('app.debug') => $e->getMessage(),
            default => Response::$statusTexts[500],
        };

        return ApiResponse::error($status, $message, null, $status)
            ->withHeaders($e instanceof HttpExceptionInterface ? $e->getHeaders() : []);
    }
}
