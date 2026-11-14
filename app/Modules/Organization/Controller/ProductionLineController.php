<?php

namespace App\Modules\Organization\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\FormRequest\StoreProductionLineRequest;
use App\Modules\Organization\FormRequest\UpdateProductionLineRequest;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Resource\ProductionLineResource;
use App\Modules\Organization\Service\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductionLineController extends Controller
{
    public function __construct(private readonly OrganizationService $organization) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->organization->paginate(
                ProductionLine::class,
                ['workshop'],
                $request->only(['workshop_id', 'status', 'keyword', 'per_page']),
                ['code', 'name'],
                ['workshop', 'workshop.parentFactory'],
            ),
            ProductionLineResource::class,
        );
    }

    public function store(StoreProductionLineRequest $request): JsonResponse
    {
        return ApiResponse::success(ProductionLineResource::make(
            ProductionLine::query()->create($request->validated())->load('workshop'),
        ));
    }

    public function update(UpdateProductionLineRequest $request, ProductionLine $productionLine): JsonResponse
    {
        return ApiResponse::success(ProductionLineResource::make(
            tap($productionLine)->update($request->validated())->load('workshop'),
        ));
    }
}
