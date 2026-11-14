<?php

namespace App\Modules\Organization\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\FormRequest\StoreProductionLineRequest;
use App\Modules\Organization\FormRequest\UpdateProductionLineRequest;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Resource\ProductionLineResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionLineController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                ProductionLine::class,
                ['workshop'],
                $request->only(['workshop_id', 'status', 'keyword', 'per_page']),
                enabledRelations: ['workshop', 'workshop.parentFactory'],
                filterColumns: ['workshop_id'],
            ),
            ProductionLineResource::class,
        );
    }

    public function store(StoreProductionLineRequest $request): JsonResponse
    {
        return ApiResponse::success(ProductionLineResource::make(
            $this->entities->create(ProductionLine::class, $request->validated())->load('workshop'),
        ));
    }

    public function update(UpdateProductionLineRequest $request, ProductionLine $productionLine): JsonResponse
    {
        return ApiResponse::success(ProductionLineResource::make(
            $this->entities->update($productionLine, $request->validated())->load('workshop'),
        ));
    }

    public function destroy(ProductionLine $productionLine): JsonResponse
    {
        // shortcut: 业务表尚未引用产线，接入生产任务后需补引用校验
        $this->entities->delete($productionLine, []);

        return ApiResponse::success();
    }
}
