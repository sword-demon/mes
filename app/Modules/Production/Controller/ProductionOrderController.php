<?php

namespace App\Modules\Production\Controller;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Services\BusinessDay;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Production\FormRequest\StoreProductionOrderRequest;
use App\Modules\Production\FormRequest\UpdateProductionOrderRequest;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Resource\ProductionOrderResource;
use App\Modules\Production\Service\ProductionOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionOrderController extends Controller
{
    private const array RELATIONS = [
        'product',
        'productVersion',
        'bom',
        'route',
        'productionLine',
    ];

    public function __construct(
        private readonly ProductionOrderService $orders,
        private readonly EntityService $entities,
        private readonly DataScopeGuard $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $startDate = BusinessDay::resolveDate($request->query('start_date'), 'start_date');
        $endDate = BusinessDay::resolveDate($request->query('end_date'), 'end_date');
        $status = $request->query('status');

        $query = ProductionOrder::query()
            ->with(self::RELATIONS)
            ->inDataScope($this->dataScope->scope())
            ->when(
                is_string($status) && $status !== '',
                fn (Builder $query) => $query->where('status', $status),
            )
            ->when($startDate !== null, fn (Builder $query) => $query->where('planned_start_at', '>=', BusinessDay::startUtc($startDate)))
            ->when($endDate !== null, fn (Builder $query) => $query->where('planned_start_at', '<', BusinessDay::endUtc($endDate)));

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                $request->only(['product_id', 'production_line_id', 'keyword', 'per_page']),
                ['code'],
                filterColumns: ['product_id', 'production_line_id'],
            ),
            ProductionOrderResource::class,
        );
    }

    public function store(StoreProductionOrderRequest $request): JsonResponse
    {
        return ApiResponse::success(ProductionOrderResource::make(
            $this->orders->create($request->validated())->load(self::RELATIONS),
        ));
    }

    public function update(UpdateProductionOrderRequest $request, ProductionOrder $order): JsonResponse
    {
        return ApiResponse::success(ProductionOrderResource::make(
            $this->orders->update($order, $request->validated())->load(self::RELATIONS),
        ));
    }

    public function submit(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'submit');
    }

    public function approve(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'approve');
    }

    public function release(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'release');
    }

    public function start(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'start');
    }

    public function pause(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'pause');
    }

    public function resume(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'resume');
    }

    public function complete(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'complete');
    }

    public function close(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'close');
    }

    public function cancel(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, 'cancel');
    }

    private function transition(ProductionOrder $order, string $action): JsonResponse
    {
        return ApiResponse::success(ProductionOrderResource::make(
            $this->orders->transition($order, $action)->load(self::RELATIONS),
        ));
    }
}
