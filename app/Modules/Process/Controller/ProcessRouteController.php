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
                enabledRelations: ['product'],
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
        $this->ensureActiveProduct($route, '提交审核');
        $this->ensureActiveProcesses($route, '提交审核');

        if ($route->audit_status !== ProcessRoute::DRAFT) {
            throw new ApiException('只有草稿状态的工艺路线才能提交审核', 422);
        }

        if (! $route->operations()->exists()) {
            throw new ApiException('工艺路线至少需要一道工序才能提交审核', 422);
        }

        return ApiResponse::success(ProcessRouteResource::make(
            $this->entities->update($route, ['audit_status' => ProcessRoute::PENDING])
                ->load('product'),
        ));
    }

    public function approve(ProcessRoute $route): JsonResponse
    {
        $this->ensureActiveProduct($route, '审核通过');
        $this->ensureActiveProcesses($route, '审核通过');

        if ($route->audit_status !== ProcessRoute::PENDING) {
            throw new ApiException('只有待审核的工艺路线才能审核通过', 422);
        }

        return ApiResponse::success(ProcessRouteResource::make(
            $this->entities->update($route, [
                'audit_status' => ProcessRoute::APPROVED,
                'audited_at' => now(),
            ])->load('product'),
        ));
    }

    private function ensureActiveProduct(ProcessRoute $route, string $action): void
    {
        $route->loadMissing('product');

        if (! $route->product->status) {
            throw new ApiException("产品已停用，不能{$action}", 422);
        }
    }

    /**
     * A process can be retired while a draft still references it; approving
     * such a route would hand production a step nobody can execute.
     */
    private function ensureActiveProcesses(ProcessRoute $route, string $action): void
    {
        if ($route->operations()->whereHas('process', fn ($query) => $query->where('status', false))->exists()) {
            throw new ApiException("工艺路线包含已停用的工序，不能{$action}", 422);
        }
    }
}
