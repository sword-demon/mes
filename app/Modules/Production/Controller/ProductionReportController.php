<?php

namespace App\Modules\Production\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\Model\Employee;
use App\Modules\Production\FormRequest\StoreProductionReportRequest;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Resource\ProductionReportResource;
use App\Modules\Production\Service\ProductionReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProductionReportController extends Controller
{
    private const array RELATIONS = [
        'productionTask.productionOrder',
        'productionTask.product',
        'routeOperation.process',
        'employee',
        'team',
    ];

    public function __construct(
        private readonly ProductionReportService $reports,
        private readonly EntityService $entities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orderId = $request->query('production_order_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $keyword = $request->query('keyword');

        $query = ProductionReport::query()
            ->with(self::RELATIONS)
            ->when(
                is_string($orderId) && $orderId !== '',
                fn (Builder $query) => $query->whereHas(
                    'productionTask',
                    fn (Builder $task) => $task->where('production_order_id', $orderId),
                ),
            )
            ->when(
                is_string($startDate) && $startDate !== '',
                fn (Builder $query) => $query->where('reported_at', '>=', Carbon::parse($startDate)->startOfDay()),
            )
            ->when(
                is_string($endDate) && $endDate !== '',
                fn (Builder $query) => $query->where('reported_at', '<', Carbon::parse($endDate)->addDay()->startOfDay()),
            )
            ->when(is_string($keyword) && $keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(fn (Builder $inner) => $inner
                    ->where('code', 'like', "%{$keyword}%")
                    ->orWhereHas('employee', fn (Builder $employee) => $employee
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('employee_no', 'like', "%{$keyword}%"))
                    ->orWhereHas('productionTask', fn (Builder $task) => $task
                        ->where('code', 'like', "%{$keyword}%")));
            });

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['production_task_id', 'employee_id', 'type', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                [],
                filterColumns: ['production_task_id', 'employee_id', 'type'],
            ),
            ProductionReportResource::class,
        );
    }

    /**
     * The report form reads its own option lists so an operator does not need
     * the task, dispatch and basic-data read permissions to submit a report.
     */
    public function options(): JsonResponse
    {
        // shortcut: one page covers a demo factory's open tasks; add paging when it does not.
        $tasks = ProductionTask::query()
            ->with(['product:id,code,name', 'productionOrder:id,code'])
            ->whereHas('productionOrder', fn (Builder $order) => $order->whereIn('status', ProductionReportService::REPORTABLE_ORDER_STATUSES))
            ->where('status', '!=', ProductionTask::COMPLETED)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (ProductionTask $task) => [
                'id' => $task->id,
                'code' => $task->code,
                'planned_quantity' => $task->planned_quantity,
                'produced_quantity' => $task->produced_quantity,
                'remaining_quantity' => number_format((float) $task->planned_quantity - (float) $task->produced_quantity, 3, '.', ''),
                'product' => $task->product?->only(['id', 'code', 'name']),
                'production_order' => $task->productionOrder?->only(['id', 'code']),
            ]);

        $employees = Employee::query()
            ->where('status', true)
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'employee_no', 'name']);

        return ApiResponse::success([
            'tasks' => $tasks,
            'employees' => $employees,
        ]);
    }

    public function store(StoreProductionReportRequest $request): JsonResponse
    {
        return ApiResponse::success(ProductionReportResource::make(
            $this->reports->create($request->validated())->load(self::RELATIONS),
        ));
    }

    public function reverse(ProductionReport $report): JsonResponse
    {
        return ApiResponse::success(ProductionReportResource::make(
            $this->reports->reverse($report)->load(self::RELATIONS),
        ));
    }
}
