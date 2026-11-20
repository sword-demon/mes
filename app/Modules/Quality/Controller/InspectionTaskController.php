<?php

namespace App\Modules\Quality\Controller;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Services\BusinessDay;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Quality\FormRequest\RecordInspectionTaskRequest;
use App\Modules\Quality\FormRequest\StoreInspectionTaskRequest;
use App\Modules\Quality\FormRequest\UploadInspectionImagesRequest;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\Quality\Resource\InspectionTaskResource;
use App\Modules\Quality\Service\InspectionTaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionTaskController extends Controller
{
    public function __construct(
        private readonly EntityService $entities,
        private readonly InspectionTaskService $tasks,
        private readonly DataScopeGuard $dataScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = InspectionTask::query()
            ->with(InspectionTaskService::RELATIONS)
            ->inDataScope($this->dataScope->scope());
        $result = $request->query('result');
        $judged = filter_var($request->query('judged'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $orderId = $request->query('production_order_id');
        $type = $request->query('inspection_type');
        $operationId = $request->query('route_operation_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $keyword = $request->query('keyword');
        $isDate = static fn (mixed $value): bool => BusinessDay::isDate($value);

        $query
            ->when(is_string($orderId) && $orderId !== '', fn (Builder $query) => $query->where('production_order_id', $orderId))
            ->when(is_string($type) && $type !== '', fn (Builder $query) => $query->where('inspection_type', $type))
            ->when($result === InspectionTask::PENDING, fn (Builder $query) => $query->whereNull('result'))
            ->when(in_array($result, [InspectionTask::PASSED, InspectionTask::FAILED], true), fn (Builder $query) => $query->where('result', $result))
            ->when($judged === true, fn (Builder $query) => $query->whereNotNull('result'))
            ->when(is_string($operationId) && $operationId !== '', fn (Builder $query) => $query->where('route_operation_id', $operationId))
            ->when($isDate($startDate), fn (Builder $query) => $query->where(function (Builder $inner) use ($startDate): void {
                $start = BusinessDay::startUtc($startDate);
                $inner->where('inspected_at', '>=', $start)
                    ->orWhere(fn (Builder $pending) => $pending
                        ->whereNull('inspected_at')
                        ->where('created_at', '>=', $start));
            }))
            ->when($isDate($endDate), fn (Builder $query) => $query->where(function (Builder $inner) use ($endDate): void {
                $end = BusinessDay::endUtc($endDate);
                $inner->where('inspected_at', '<', $end)
                    ->orWhere(fn (Builder $pending) => $pending
                        ->whereNull('inspected_at')
                        ->where('created_at', '<', $end));
            }))
            ->when(is_string($keyword) && $keyword !== '', fn (Builder $query) => $query->whereHas(
                'productionOrder',
                fn (Builder $order) => $order->where('code', 'like', "%{$keyword}%"),
            ));

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
            ),
            InspectionTaskResource::class,
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
        $hasOrder = is_string($orderId) && $orderId !== '';
        $order = $hasOrder ? ProductionOrder::query()->find($orderId) : null;
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
        $records = ProcessRecord::query()
            ->with([
                'routeOperation.process',
                'inspectionTasks' => fn ($query) => $query->whereNull('result'),
            ])
            ->when($hasOrder, fn (Builder $query) => $query->whereHas(
                'productionTask',
                fn (Builder $task) => $task->where('production_order_id', $orderId),
            ))
            ->when(! $hasOrder, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('status', ProcessRecord::INSPECTING)
            ->orderBy('sequence')
            ->get()
            ->reject(fn (ProcessRecord $record): bool => $record->inspectionTasks
                ->contains('inspection_cycle', $record->inspection_cycle))
            ->map(fn (ProcessRecord $record): array => [
                'id' => $record->id,
                'sequence' => $record->sequence,
                'sn' => $record->sn,
                'route_operation' => [
                    'id' => $record->routeOperation->id,
                    'sequence' => $record->routeOperation->sequence,
                    'process' => [
                        'id' => $record->routeOperation->process->id,
                        'name' => $record->routeOperation->process->name,
                    ],
                ],
            ]);

        return ApiResponse::success([
            'orders' => $orders,
            'operations' => $operations,
            'process_records' => $records,
        ]);
    }

    public function store(StoreInspectionTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->validated());

        return ApiResponse::success(
            InspectionTaskResource::make($task->load(InspectionTaskService::RELATIONS)),
        );
    }

    public function record(RecordInspectionTaskRequest $request, InspectionTask $task): JsonResponse
    {
        return ApiResponse::success(
            InspectionTaskResource::make($this->tasks->record($task, $request->validated(), $request->user())),
        );
    }

    public function storeImages(UploadInspectionImagesRequest $request, InspectionTask $task): JsonResponse
    {
        $this->tasks->addImages($task, (array) $request->file('images'));

        return ApiResponse::success(
            InspectionTaskResource::make($task->load(InspectionTaskService::RELATIONS)),
        );
    }
}
