<?php

namespace App\Modules\Production\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Production\FormRequest\ProcessProductionExceptionRequest;
use App\Modules\Production\FormRequest\StoreProductionExceptionRequest;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionException;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Resource\ProductionExceptionResource;
use App\Modules\Production\Service\ProductionExceptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ProductionExceptionController extends Controller
{
    public function __construct(private readonly ProductionExceptionService $exceptions) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(ProductionException::TYPES)],
            'status' => ['sometimes', Rule::in(ProductionException::STATUSES)],
            'production_order_id' => ['sometimes', 'integer', 'min:1', 'exists:mes_production_orders,id'],
            'start_date' => ['sometimes', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = ProductionException::query()
            ->with(ProductionException::RELATIONS)
            ->when(
                $data['type'] ?? null,
                fn (Builder $query, string $type) => $query->where('type', $type),
            )
            ->when(
                $data['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('status', $status),
            )
            ->when(
                $data['production_order_id'] ?? null,
                fn (Builder $query, int $orderId) => $query->where('production_order_id', $orderId),
            )
            ->when(
                $data['start_date'] ?? null,
                fn (Builder $query, string $date) => $query->where('reported_at', '>=', $this->businessDayStart($date)),
            )
            ->when(
                $data['end_date'] ?? null,
                fn (Builder $query, string $date) => $query->where('reported_at', '<', $this->businessDayStart($date)->addDay()),
            )
            ->orderByDesc('reported_at')
            ->orderByDesc('id');

        return ApiResponse::paginated(
            $query->paginate(max(1, min((int) ($data['per_page'] ?? 15), 100))),
            ProductionExceptionResource::class,
        );
    }

    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'production_order_id' => ['sometimes', 'integer', 'min:1', 'exists:mes_production_orders,id'],
        ]);
        $orderId = $data['production_order_id'] ?? null;
        $hasOrder = $orderId !== null;

        $orders = ProductionOrder::query()
            ->with('product')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ProductionOrder $order): array => [
                'id' => $order->id,
                'code' => $order->code,
                'product' => [
                    'id' => $order->product->id,
                    'code' => $order->product->code,
                    'name' => $order->product->name,
                ],
            ]);

        $query = ProcessRecord::query()
            ->with(['routeOperation.process', 'productionTask.productionOrder'])
            ->when(
                $hasOrder,
                fn (Builder $query) => $query
                    ->whereHas(
                        'productionTask',
                        fn (Builder $task) => $task->where('production_order_id', $orderId),
                    )
                    ->orderBy('production_task_id')
                    ->orderBy('sequence'),
            )
            ->when(! $hasOrder, fn (Builder $query) => $query->orderByDesc('id'))
            ->limit($hasOrder ? 200 : 100);

        $records = $query->get()
            ->map(fn (ProcessRecord $record): array => [
                'id' => $record->id,
                'sequence' => $record->sequence,
                'sn' => $record->sn,
                'production_order' => [
                    'id' => $record->productionTask->productionOrder->id,
                    'code' => $record->productionTask->productionOrder->code,
                ],
                'route_operation' => [
                    'id' => $record->routeOperation->id,
                    'sequence' => $record->routeOperation->sequence,
                    'process' => [
                        'id' => $record->routeOperation->process->id,
                        'code' => $record->routeOperation->process->code,
                        'name' => $record->routeOperation->process->name,
                    ],
                ],
            ]);

        return ApiResponse::success([
            'orders' => $orders,
            'process_records' => $records,
        ]);
    }

    public function store(StoreProductionExceptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $exception = $this->exceptions->report(
            [
                'type' => (string) $data['type'],
                'production_order_id' => isset($data['production_order_id']) ? (int) $data['production_order_id'] : null,
                'process_record_id' => isset($data['process_record_id']) ? (int) $data['process_record_id'] : null,
                'description' => (string) $data['description'],
            ],
            $request->user(),
            (array) $request->file('images', []),
        );

        return ApiResponse::success(ProductionExceptionResource::make($exception));
    }

    public function process(ProcessProductionExceptionRequest $request, ProductionException $exception): JsonResponse
    {
        return ApiResponse::success(ProductionExceptionResource::make(
            $this->exceptions->process(
                $exception,
                $request->user(),
                (string) $request->validated('handling_result'),
            ),
        ));
    }

    public function close(Request $request, ProductionException $exception): JsonResponse
    {
        $data = $request->validate([
            'handling_result' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return ApiResponse::success(ProductionExceptionResource::make(
            $this->exceptions->close($exception, $data['handling_result'] ?? null),
        ));
    }

    private function businessDayStart(string $date): Carbon
    {
        return Carbon::parse($date, (string) config('app.business_timezone', 'Asia/Shanghai'))
            ->startOfDay()
            ->utc();
    }
}
