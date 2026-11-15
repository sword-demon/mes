<?php

namespace App\Modules\Production\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\Model\Employee;
use App\Modules\Production\FormRequest\BindProductSnRequest;
use App\Modules\Production\FormRequest\StoreProductSnRequest;
use App\Modules\Production\FormRequest\VoidProductSnRequest;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Production\Resource\ProductSnResource;
use App\Modules\Production\Service\ProductSnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSnController extends Controller
{
    private const array RELATIONS = [
        'productionOrder.product',
        'productionBatch',
        'employee',
    ];

    private const array DETAIL_RELATIONS = [
        'productionOrder.product',
        'productionBatch',
        'employee',
        'processRecords.routeOperation.process',
        'processRecords.employee',
    ];

    public function __construct(
        private readonly ProductSnService $sns,
        private readonly EntityService $entities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $keyword = $request->query('keyword');
        $status = $request->query('status');

        $query = ProductSn::query()
            ->with(self::RELATIONS)
            ->when(
                is_string($status) && $status !== '',
                fn (Builder $query) => $query->where('status', $status),
            )
            ->when(
                is_string($keyword) && $keyword !== '',
                fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                    ->where('sn', 'like', "%{$keyword}%")
                    ->orWhere('material_batch_no', 'like', "%{$keyword}%")
                    ->orWhereHas('productionOrder', fn (Builder $order) => $order->where('code', 'like', "%{$keyword}%"))
                    ->orWhereHas('productionBatch', fn (Builder $batch) => $batch->where('code', 'like', "%{$keyword}%"))),
            );

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['production_order_id', 'production_batch_id', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                [],
                filterColumns: ['production_order_id', 'production_batch_id'],
            ),
            ProductSnResource::class,
        );
    }

    /**
     * The generate form reads its own options so a warehouse user does not need
     * the production-order read permission to issue serial numbers.
     */
    public function options(): JsonResponse
    {
        // shortcut: one page covers a demo factory's open orders; add paging when it does not.
        $orders = ProductionOrder::query()
            ->with('product:id,code,name')
            ->whereIn('status', ProductSnService::GENERATABLE_ORDER_STATUSES)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'code', 'product_id', 'planned_quantity'])
            ->map(fn (ProductionOrder $order) => [
                'id' => $order->id,
                'code' => $order->code,
                'planned_quantity' => $order->planned_quantity,
                'product' => $order->product?->only(['id', 'code', 'name']),
            ]);

        $employees = Employee::query()
            ->where('status', true)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'employee_no', 'name']);

        return ApiResponse::success([
            'orders' => $orders,
            'employees' => $employees,
        ]);
    }

    public function store(StoreProductSnRequest $request): JsonResponse
    {
        $result = $this->sns->generate($request->validated());
        $batch = $result['batch'];

        return ApiResponse::success([
            'batch' => [
                'id' => $batch->id,
                'code' => $batch->code,
                'quantity' => $batch->quantity,
                'production_order_id' => $batch->production_order_id,
            ],
            'items' => ProductSnResource::collection($result['sns']->load(self::RELATIONS))->resolve(),
        ]);
    }

    public function show(ProductSn $productSn): JsonResponse
    {
        return ApiResponse::success(
            ProductSnResource::make($productSn->load(self::DETAIL_RELATIONS)),
        );
    }

    public function bind(BindProductSnRequest $request, ProductSn $productSn): JsonResponse
    {
        return $this->respond($this->sns->bind($productSn, $request->validated()));
    }

    public function label(ProductSn $productSn): JsonResponse
    {
        $sn = $this->sns->prepareLabel($productSn);

        return ApiResponse::success([
            'sn' => ProductSnResource::make($sn)->resolve(),
            'label' => [
                'sn' => $sn->sn,
                'product_code' => $sn->productionOrder->product->code,
                'product_name' => $sn->productionOrder->product->name,
                'production_order' => $sn->productionOrder->code,
                'production_batch' => $sn->productionBatch->code,
                'generated_at' => $sn->created_at,
            ],
        ]);
    }

    public function markPrinted(ProductSn $productSn): JsonResponse
    {
        return $this->respond($this->sns->print($productSn));
    }

    public function void(VoidProductSnRequest $request, ProductSn $productSn): JsonResponse
    {
        return $this->respond($this->sns->void($productSn, (string) $request->validated('reason')));
    }

    private function respond(ProductSn $sn): JsonResponse
    {
        return ApiResponse::success(ProductSnResource::make($sn->load(self::RELATIONS)));
    }
}
