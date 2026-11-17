<?php

namespace App\Modules\Material\Controller;

use App\Core\Services\BusinessDay;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Material\FormRequest\ApproveMaterialReturnRequest;
use App\Modules\Material\FormRequest\StoreMaterialReturnRequest;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialReturn;
use App\Modules\Material\Resource\MaterialReturnResource;
use App\Modules\Material\Service\MaterialReturnService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialReturnController extends Controller
{
    public function __construct(
        private readonly MaterialReturnService $returns,
        private readonly EntityService $entities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $materialId = $request->query('material_id');
        $dateFrom = BusinessDay::resolveDate($request->query('date_from'), 'date_from');
        $dateTo = BusinessDay::resolveDate($request->query('date_to'), 'date_to');

        $query = MaterialReturn::query()
            ->with(['productionOrder', 'requisition', 'items.requisitionItem.material', 'approvedBy'])
            ->orderByDesc('id')
            ->when(is_scalar($materialId), fn (Builder $query) => $query->whereHas(
                'items.requisitionItem',
                fn (Builder $items) => $items->where('material_id', $materialId),
            ))
            ->when($dateFrom !== null, fn (Builder $query) => $query->where('created_at', '>=', BusinessDay::startUtc($dateFrom)))
            ->when($dateTo !== null, fn (Builder $query) => $query->where('created_at', '<', BusinessDay::endUtc($dateTo)));

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['production_order_id', 'status', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                [],
                filterColumns: ['production_order_id', 'status'],
            ),
            MaterialReturnResource::class,
        );
    }

    public function suggestions(Request $request): JsonResponse
    {
        $requisition = MaterialRequisition::query()->findOrFail($request->integer('requisition_id'));

        return ApiResponse::success($this->returns->suggestions($requisition));
    }

    public function store(StoreMaterialReturnRequest $request): JsonResponse
    {
        return ApiResponse::success(MaterialReturnResource::make(
            $this->returns->create($request->validated(), $request->user()->id),
        ));
    }

    public function approve(ApproveMaterialReturnRequest $request, MaterialReturn $materialReturn): JsonResponse
    {
        return ApiResponse::success(MaterialReturnResource::make(
            $this->returns->approve($materialReturn, $request->validated(), $request->user()->id),
        ));
    }

    public function cancel(Request $request, MaterialReturn $materialReturn): JsonResponse
    {
        return ApiResponse::success(MaterialReturnResource::make(
            $this->returns->cancel($materialReturn, $request->user()->id),
        ));
    }
}
