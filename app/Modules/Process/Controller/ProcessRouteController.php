<?php

namespace App\Modules\Process\Controller;

use App\Core\Exceptions\ApiException;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Process\FormRequest\StoreProcessRouteRequest;
use App\Modules\Process\FormRequest\UpdateProcessRouteRequest;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Resource\ProcessRouteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessRouteController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                ProcessRoute::class,
                ['product'],
                $request->only(['product_id', 'audit_status', 'status', 'keyword', 'per_page']),
                ['code', 'version'],
                // `status=1` feeds the dropdowns, so a route whose product or
                // any of its processes was retired must not look selectable.
                enabledRelations: ['product', 'operations.process'],
                filterColumns: ['product_id', 'audit_status'],
            ),
            ProcessRouteResource::class,
        );
    }

    public function store(StoreProcessRouteRequest $request): JsonResponse
    {
        return ApiResponse::success(ProcessRouteResource::make(
            $this->entities->create(ProcessRoute::class, $request->validated())
                ->load('product'),
        ));
    }

    public function update(UpdateProcessRouteRequest $request, ProcessRoute $route): JsonResponse
    {
        $data = $request->validated();

        $route->ensureEditable(array_keys($data));

        return ApiResponse::success(ProcessRouteResource::make(
            $this->entities->update($route, $data)->load('product'),
        ));
    }

    public function submit(ProcessRoute $route): JsonResponse
    {
        $route->ensureAuditable('提交审核');

        if ($route->audit_status !== ProcessRoute::DRAFT) {
            throw new ApiException('只有草稿状态的工艺路线才能提交审核', 422);
        }

        if (! $route->operations()->exists()) {
            throw new ApiException('工艺路线至少需要一道工序才能提交审核', 422);
        }

        return $this->transition($route, ['audit_status' => ProcessRoute::PENDING]);
    }

    public function approve(ProcessRoute $route): JsonResponse
    {
        $route->ensureAuditable('审核通过');

        if ($route->audit_status !== ProcessRoute::PENDING) {
            throw new ApiException('只有待审核的工艺路线才能审核通过', 422);
        }

        return $this->transition($route, [
            'audit_status' => ProcessRoute::APPROVED,
            'audited_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transition(ProcessRoute $route, array $data): JsonResponse
    {
        return ApiResponse::success(ProcessRouteResource::make(
            $this->entities->update($route, $data)->load('product'),
        ));
    }
}
