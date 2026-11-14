<?php

namespace App\Modules\Organization\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\FormRequest\StoreFactoryRequest;
use App\Modules\Organization\FormRequest\UpdateFactoryRequest;
use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Resource\FactoryResource;
use App\Modules\Organization\Service\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FactoryController extends Controller
{
    public function __construct(private readonly OrganizationService $organization) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->organization->paginate(Factory::class, [], $request->only(['status', 'keyword', 'per_page'])),
            FactoryResource::class,
        );
    }

    public function store(StoreFactoryRequest $request): JsonResponse
    {
        return ApiResponse::success(FactoryResource::make(
            $this->organization->create(Factory::class, $request->validated()),
        ));
    }

    public function update(UpdateFactoryRequest $request, Factory $factory): JsonResponse
    {
        return ApiResponse::success(FactoryResource::make(
            $this->organization->update($factory, $request->validated()),
        ));
    }

    public function destroy(Factory $factory): JsonResponse
    {
        $this->organization->delete($factory, [
            'workshops' => '工厂下还有车间，只能停用',
        ]);

        return ApiResponse::success();
    }
}
