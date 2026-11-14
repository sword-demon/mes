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
                enabledRelations: ['parentFactory'],
                filterColumns: ['factory_id'],
            ),
            WorkshopResource::class,
        );
    }

    public function store(StoreWorkshopRequest $request): JsonResponse
    {
        return ApiResponse::success(WorkshopResource::make(
            $this->organization->create(Workshop::class, $request->validated())->load('parentFactory'),
        ));
    }

    public function update(UpdateWorkshopRequest $request, Workshop $workshop): JsonResponse
    {
        return ApiResponse::success(WorkshopResource::make(
            $this->organization->update($workshop, $request->validated())->load('parentFactory'),
        ));
    }

    public function destroy(Workshop $workshop): JsonResponse
    {
        $this->organization->delete($workshop, [
            'productionLines' => '车间下还有产线，只能停用',
            'teams' => '车间下还有班组，只能停用',
        ]);

        return ApiResponse::success();
    }
}
