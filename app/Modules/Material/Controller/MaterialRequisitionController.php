<?php

namespace App\Modules\Material\Controller;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Services\BusinessDay;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Material\FormRequest\IssueMaterialRequisitionRequest;
use App\Modules\Material\FormRequest\StoreMaterialRequisitionRequest;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Resource\MaterialRequisitionResource;
use App\Modules\Material\Service\MaterialRequisitionService;
use App\Modules\Production\Model\ProductionOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialRequisitionController extends Controller
{
    public function __construct(
        private readonly DataScopeGuard $dataScope,
        private readonly MaterialRequisitionService $requisitions,
        private readonly EntityService $entities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $materialId = $request->query('material_id');
        $dateFrom = BusinessDay::resolveDate($request->query('date_from'), 'date_from');
        $dateTo = BusinessDay::resolveDate($request->query('date_to'), 'date_to');

        $query = MaterialRequisition::query()
            ->inDataScope($this->dataScope->scope())
            ->with(['productionOrder', 'items.material'])
            ->orderByDesc('id')
            ->when(is_scalar($materialId), fn (Builder $query) => $query->whereHas(
                'items',
                fn (Builder $items) => $items->where('material_id', $materialId),
            ))
            ->when($dateFrom !== null, fn (Builder $query) => $query->where('created_at', '>=', BusinessDay::startUtc($dateFrom)))
            ->when($dateTo !== null, fn (Builder $query) => $query->where('created_at', '<', BusinessDay::endUtc($dateTo)));

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['production_order_id', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                [],
                filterColumns: ['production_order_id'],
            ),
            MaterialRequisitionResource::class,
        );
    }

    public function suggestions(Request $request): JsonResponse
    {
        $order = ProductionOrder::query()
            ->inDataScope($this->dataScope->scope())
            ->findOrFail($request->integer('production_order_id'));

        return ApiResponse::success($this->requisitions->suggestions($order));
    }

    public function store(StoreMaterialRequisitionRequest $request): JsonResponse
    {
        return ApiResponse::success(MaterialRequisitionResource::make(
            $this->requisitions->create($request->validated(), $request->user()->id),
        ));
    }

    public function issue(IssueMaterialRequisitionRequest $request, MaterialRequisition $requisition): JsonResponse
    {
        return ApiResponse::success(MaterialRequisitionResource::make(
            $this->requisitions->issue($requisition, $request->validated(), $request->user()->id),
        ));
    }
}
