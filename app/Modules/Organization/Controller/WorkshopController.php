<?php

namespace App\Modules\Organization\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\FormRequest\StoreWorkshopRequest;
use App\Modules\Organization\FormRequest\UpdateWorkshopRequest;
use App\Modules\Organization\Model\Workshop;
use App\Modules\Organization\Resource\WorkshopResource;
use App\Modules\Organization\Service\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    public function __construct(private readonly OrganizationService $organization) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->organization->paginate(
                Workshop::class,
                ['parentFactory'],
                $request->only(['factory_id', 'status', 'keyword', 'per_page']),
                ['code', 'name'],
                ['parentFactory'],
            ),
            WorkshopResource::class,
        );
    }

    public function store(StoreWorkshopRequest $request): JsonResponse
    {
        return ApiResponse::success(WorkshopResource::make(
            Workshop::query()->create($request->validated())->load('parentFactory'),
        ));
    }

    public function update(UpdateWorkshopRequest $request, Workshop $workshop): JsonResponse
    {
        return ApiResponse::success(WorkshopResource::make(
            tap($workshop)->update($request->validated())->load('parentFactory'),
        ));
    }
}
