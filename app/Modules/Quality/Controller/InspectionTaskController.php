<?php

namespace App\Modules\Quality\Controller;

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
use Illuminate\Support\Facades\DB;

class InspectionTaskController extends Controller
{
    public function __construct(
        private readonly EntityService $entities,
        private readonly InspectionTaskService $tasks,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = InspectionTask::query()->with(InspectionTaskService::RELATIONS);
        $result = $request->query('result');
        $judged = filter_var($request->query('judged'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $orderId = $request->query('production_order_id');
        $type = $request->query('inspection_type');
        $operationId = $request->query('route_operation_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $keyword = $request->query('keyword');
        $isDate = static fn (mixed $value): bool => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;

        $query
            ->when(is_string($orderId) && $orderId !== '', fn (Builder $query) => $query->where('production_order_id', $orderId))
            ->when(is_string($type) && $type !== '', fn (Builder $query) => $query->where('inspection_type', $type))
            ->when($result === InspectionTask::PENDING, fn (Builder $query) => $query->whereNull('result'))
            ->when(in_array($result, [InspectionTask::PASSED, InspectionTask::FAILED], true), fn (Builder $query) => $query->where('result', $result))
            ->when($judged === true, fn (Builder $query) => $query->whereNotNull('result'))
            ->when(is_string($operationId) && $operationId !== '', fn (Builder $query) => $query->where('route_operation_id', $operationId))
            ->when($isDate($startDate), fn (Builder $query) => $query->where(function (Builder $inner) use ($startDate): void {
                $inner->where('inspected_at', '>=', "{$startDate} 00:00:00")
                    ->orWhere(fn (Builder $pending) => $pending
                        ->whereNull('inspected_at')
                        ->where('created_at', '>=', "{$startDate} 00:00:00"));
            }))
            ->when($isDate($endDate), fn (Builder $query) => $query->where(function (Builder $inner) use ($endDate): void {
                $inner->where('inspected_at', '<=', "{$endDate} 23:59:59")
                    ->orWhere(fn (Builder $pending) => $pending
                        ->whereNull('inspected_at')
                        ->where('created_at', '<=', "{$endDate} 23:59:59"));
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
        $orders = ProductionOrder::query()
            ->with('product')
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
            ->with('routeOperation.process')
            ->when($hasOrder, fn (Builder $query) => $query->whereHas(
                'productionTask',
                fn (Builder $task) => $task->where('production_order_id', $orderId),
            ))
            ->when(! $hasOrder, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where('status', ProcessRecord::INSPECTING)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('mes_inspection_tasks')
                    ->whereColumn('mes_inspection_tasks.process_record_id', 'mes_process_records.id')
                    ->whereNull('mes_inspection_tasks.result')
                    ->whereColumn(
                        'mes_inspection_tasks.created_at',
                        '>',
                        DB::raw('COALESCE(mes_process_records.completed_at, mes_process_records.created_at)'),
                    );
            })
            ->orderBy('sequence')
            ->get()
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
