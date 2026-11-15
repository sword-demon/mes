<?php

namespace App\Modules\Production\Controller;

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
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ProductionOrder::query()
            ->with(self::RELATIONS)
            ->when($request->query('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->query('start_date'), fn (Builder $query, string $date) => $query->whereDate('planned_start_at', '>=', $date))
            ->when($request->query('end_date'), fn (Builder $query, string $date) => $query->whereDate('planned_start_at', '<=', $date));

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
        return $this->transition($order, ProductionOrder::PENDING);
    }

    public function approve(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, ProductionOrder::APPROVED);
    }

    public function release(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, ProductionOrder::RELEASED);
    }

    public function start(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, ProductionOrder::IN_PROGRESS);
    }

    public function pause(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, ProductionOrder::PAUSED);
    }

    public function resume(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, ProductionOrder::IN_PROGRESS);
    }

    public function complete(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, ProductionOrder::COMPLETED);
    }

    public function close(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, ProductionOrder::CLOSED);
    }

    public function cancel(ProductionOrder $order): JsonResponse
    {
        return $this->transition($order, ProductionOrder::CANCELLED);
    }

    private function transition(ProductionOrder $order, string $target): JsonResponse
    {
        return ApiResponse::success(ProductionOrderResource::make(
            $this->orders->transition($order, $target)->load(self::RELATIONS),
        ));
    }
}
