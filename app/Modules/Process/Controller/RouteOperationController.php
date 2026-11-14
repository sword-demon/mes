<?php

namespace App\Modules\Process\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Process\FormRequest\StoreRouteOperationRequest;
use App\Modules\Process\FormRequest\UpdateRouteOperationRequest;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Process\Resource\RouteOperationResource;
use Illuminate\Http\JsonResponse;

class RouteOperationController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    /**
     * The whole route is returned in execution order, since skipping a step
     * only makes sense when the caller sees every step.
     */
    public function index(ProcessRoute $route): JsonResponse
    {
        return ApiResponse::success(RouteOperationResource::collection(
            $route->operations()->with('process')->get(),
        ));
    }

    public function store(StoreRouteOperationRequest $request, ProcessRoute $route): JsonResponse
    {
        $route->ensureEditable();

        return ApiResponse::success(RouteOperationResource::make(
            $this->entities->create(RouteOperation::class, array_merge(
                $request->validated(),
                ['route_id' => $route->id],
            ))->load('process'),
        ));
    }

    public function update(UpdateRouteOperationRequest $request, ProcessRoute $route, RouteOperation $operation): JsonResponse
    {
        $route->ensureEditable();

        return ApiResponse::success(RouteOperationResource::make(
            $this->entities->update($operation, $request->validated())->load('process'),
        ));
    }

    public function destroy(ProcessRoute $route, RouteOperation $operation): JsonResponse
    {
        $route->ensureEditable();

        $this->entities->delete($operation, []);

        return ApiResponse::success();
    }
}
