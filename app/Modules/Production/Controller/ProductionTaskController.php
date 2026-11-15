<?php

namespace App\Modules\Production\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Production\FormRequest\StoreProductionTaskRequest;
use App\Modules\Production\FormRequest\UpdateProductionTaskRequest;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Resource\ProductionTaskResource;
use App\Modules\Production\Service\ProductionTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionTaskController extends Controller
{
    private const array RELATIONS = [
        'productionOrder',
        'product',
        'route',
        'productionLine',
        'team',
        'owner',
    ];

    public function __construct(
        private readonly ProductionTaskService $tasks,
        private readonly EntityService $entities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ProductionTask::query()->with(self::RELATIONS);
        $status = $request->query('status');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if (is_string($startDate) && $startDate !== '') {
            $query->whereDate('planned_start_at', '>=', $startDate);
        }

        if (is_string($endDate) && $endDate !== '') {
            $query->whereDate('planned_start_at', '<=', $endDate);
        }

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                $request->only([
                    'production_order_id',
                    'production_line_id',
                    'team_id',
                    'keyword',
                    'per_page',
                ]),
                ['code'],
                filterColumns: ['production_order_id', 'production_line_id', 'team_id'],
            ),
            ProductionTaskResource::class,
        );
    }

    public function store(StoreProductionTaskRequest $request, ProductionOrder $order): JsonResponse
    {
        return ApiResponse::success(ProductionTaskResource::make(
            $this->tasks->split($order, $request->validated())->load(self::RELATIONS),
        ));
    }

    public function update(UpdateProductionTaskRequest $request, ProductionTask $task): JsonResponse
    {
        return ApiResponse::success(ProductionTaskResource::make(
            $this->tasks->assign($task, $request->validated())->load(self::RELATIONS),
        ));
    }

    public function pause(ProductionTask $task): JsonResponse
    {
        return $this->transition($task, ProductionTask::PAUSED);
    }

    public function resume(ProductionTask $task): JsonResponse
    {
        return $this->transition($task, ProductionTask::IN_PROGRESS);
    }

    public function complete(ProductionTask $task): JsonResponse
    {
        return $this->transition($task, ProductionTask::COMPLETED);
    }

    private function transition(ProductionTask $task, string $target): JsonResponse
    {
        return ApiResponse::success(ProductionTaskResource::make(
            $this->tasks->transition($task, $target)->load(self::RELATIONS),
        ));
    }
}
