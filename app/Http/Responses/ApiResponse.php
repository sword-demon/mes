<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

final class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'success'): JsonResponse
    {
        return self::respond(200, $message, $data, 200);
    }

    public static function error(int $code, string $message, mixed $data = null, int $status = 400): JsonResponse
    {
        return self::respond($code, $message, $data, $status);
    }

    /**
     * Wrap a paginated collection so `data.items` and `data.meta` stay stable.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(LengthAwarePaginator $paginator, string $resource): JsonResponse
    {
        return self::success([
            'items' => $resource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    private static function respond(int $code, string $message, mixed $data, int $status): JsonResponse
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()->timestamp,
        ], $status);
    }
}
