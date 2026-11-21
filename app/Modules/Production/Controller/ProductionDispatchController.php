<?php

namespace App\Modules\Production\Controller;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Services\BusinessDay;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Process\Resource\RouteOperationResource;
use App\Modules\Production\FormRequest\StoreProductionDispatchRequest;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Resource\ProductionDispatchResource;
use App\Modules\Production\Service\ProductionDispatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductionDispatchController extends Controller
{
    private const array RELATIONS = [
        'productionTask.productionOrder',
        'routeOperation.process',
        'employee',
        'team',
    ];

    public function __construct(
        private readonly ProductionDispatchService $dispatches,
        private readonly EntityService $entities,
        private readonly DataScopeGuard $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orderId = $request->query('production_order_id');
        $startDate = BusinessDay::resolveDate($request->query('start_date'), 'start_date');
        $endDate = BusinessDay::resolveDate($request->query('end_date'), 'end_date');
        $keyword = $request->query('keyword');

        $query = ProductionDispatch::query()
            ->with(self::RELATIONS)
            ->inDataScope($this->dataScope->scope())
            ->when(
                is_string($orderId) && $orderId !== '',
                fn (Builder $query) => $query->whereHas(
                    'productionTask',
                    fn (Builder $task) => $task->where('production_order_id', $orderId),
                ),
            )
            ->when(
                $startDate !== null,
                fn (Builder $query) => $query->where('dispatched_at', '>=', BusinessDay::startUtc($startDate)),
            )
            ->when(
                $endDate !== null,
                fn (Builder $query) => $query->where('dispatched_at', '<', BusinessDay::endUtc($endDate)),
            )
            ->when(is_string($keyword) && $keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(fn (Builder $inner) => $inner
                    ->whereHas('employee', fn (Builder $employee) => $employee
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('employee_no', 'like', "%{$keyword}%"))
                    ->orWhereHas('productionTask', fn (Builder $task) => $task
                        ->where('code', 'like', "%{$keyword}%")));
            });

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['production_task_id', 'employee_id', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                [],
                filterColumns: ['production_task_id', 'employee_id'],
            ),
            ProductionDispatchResource::class,
        );
    }

    public function mine(Request $request): JsonResponse
    {
        $employeeId = $request->user()->employee_id;

        if ($employeeId === null) {
            return ApiResponse::paginated(
                new LengthAwarePaginator([], 0, max(1, min((int) $request->query('per_page', 15), 100))),
                ProductionDispatchResource::class,
            );
        }

        $query = ProductionDispatch::query()
            ->with(self::RELATIONS)
            ->inDataScope($this->dataScope->scope())
            ->where('employee_id', $employeeId)
            ->whereHas(
                'productionTask',
                fn (Builder $task) => $task->where('status', '!=', ProductionTask::COMPLETED),
            );

        return ApiResponse::paginated(
            $this->entities->paginateQuery($query, $request->only(['per_page'])),
            ProductionDispatchResource::class,
        );
    }

    public function store(StoreProductionDispatchRequest $request): JsonResponse
    {
        return ApiResponse::success(ProductionDispatchResource::make(
            $this->dispatches->create($request->validated())->load(self::RELATIONS),
        ));
    }

    public function operations(ProductionTask $task): JsonResponse
    {
        return ApiResponse::success(
            RouteOperationResource::collection(
                RouteOperation::query()
                    ->with('process')
                    ->where('route_id', $task->route_id)
                    ->orderBy('sequence')
                    ->get(),
            )->resolve(),
        );
    }
}
