<?php

namespace App\Modules\Quality\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Quality\FormRequest\RecheckDefectiveProductRequest;
use App\Modules\Quality\FormRequest\ReworkDefectiveProductRequest;
use App\Modules\Quality\FormRequest\ScrapDefectiveProductRequest;
use App\Modules\Quality\FormRequest\StoreDefectiveProductRequest;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Resource\DefectiveProductResource;
use App\Modules\Quality\Service\DefectiveProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DefectiveProductController extends Controller
{
    public function __construct(
        private readonly EntityService $entities,
        private readonly DefectiveProductService $defects,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = DefectiveProduct::query()->with(DefectiveProductService::RELATIONS);
        $status = $request->query('status');
        $orderId = $request->query('production_order_id');

        $query
            ->when(
                is_string($status) && array_key_exists($status, DefectiveProduct::STATUS_LABELS),
                fn (Builder $query) => $query->where('status', $status),
            )
            ->when(
                is_string($orderId) && $orderId !== '',
                fn (Builder $query) => $query->where('production_order_id', $orderId),
            );

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
            ),
            DefectiveProductResource::class,
        );
    }

    public function options(Request $request): JsonResponse
    {
        $orderId = $request->query('production_order_id');
        $keyword = $request->query('keyword');
        $orders = ProductionOrder::query()
            ->with('product')
            ->when(is_string($keyword) && $keyword !== '', fn (Builder $query) => $query->where('code', 'like', "%{$keyword}%"))
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ProductionOrder $order): array => [
                'id' => $order->id,
                'code' => $order->code,
                'product' => ['id' => $order->product->id, 'code' => $order->product->code, 'name' => $order->product->name],
            ]);
        $order = is_string($orderId) && $orderId !== '' ? ProductionOrder::query()->find($orderId) : null;
        $operations = RouteOperation::query()
            ->when($order !== null, fn (Builder $query) => $query->where('route_id', $order->route_id))
            ->when($order === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->with('process')
            ->orderBy('sequence')
            ->get()
            ->map(fn (RouteOperation $operation): array => [
                'id' => $operation->id,
                'sequence' => $operation->sequence,
                'process' => ['id' => $operation->process->id, 'name' => $operation->process->name],
            ]);
        $batches = ProductionBatch::query()
            ->when($order !== null, fn (Builder $query) => $query->where('production_order_id', $order->id))
            ->when($order === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->orderBy('code')
            ->get()
            ->map(fn (ProductionBatch $batch): array => [
                'id' => $batch->id,
                'code' => $batch->code,
                'quantity' => $batch->quantity,
            ]);
        $defectCodes = DefectCode::query()
            ->where('status', true)
            ->orderBy('code')
            ->get()
            ->map(fn (DefectCode $code): array => [
                'id' => $code->id,
                'code' => $code->code,
                'name' => $code->name,
                'category' => $code->category,
            ]);

        return ApiResponse::success([
            'orders' => $orders,
            'operations' => $operations,
            'batches' => $batches,
            'defect_codes' => $defectCodes,
        ]);
    }

    public function store(StoreDefectiveProductRequest $request): JsonResponse
    {
        return ApiResponse::success(
            DefectiveProductResource::make($this->defects->create($request->validated())),
        );
    }

    public function start(Request $request, DefectiveProduct $defective): JsonResponse
    {
        return ApiResponse::success(
            DefectiveProductResource::make($this->defects->start($defective, $request->user())),
        );
    }

    public function rework(ReworkDefectiveProductRequest $request, DefectiveProduct $defective): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            DefectiveProductResource::make($this->defects->rework(
                $defective,
                (string) $data['reason'],
                $request->user(),
            )),
        );
    }

    public function scrap(ScrapDefectiveProductRequest $request, DefectiveProduct $defective): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            DefectiveProductResource::make($this->defects->scrap(
                $defective,
                (string) $data['reason'],
                (string) $data['quantity'],
                $request->user(),
            )),
        );
    }

    public function recheck(RecheckDefectiveProductRequest $request, DefectiveProduct $defective): JsonResponse
    {
        $data = $request->validated();

        return ApiResponse::success(
            DefectiveProductResource::make($this->defects->recheck(
                $defective,
                (bool) $data['passed'],
                isset($data['remark']) ? (string) $data['remark'] : null,
                $request->user(),
            )),
        );
    }
}
