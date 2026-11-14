<?php

namespace App\Modules\Organization\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\FormRequest\StoreFactoryRequest;
use App\Modules\Organization\FormRequest\UpdateFactoryRequest;
use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Resource\FactoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FactoryController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(Factory::class, [], $request->only(['status', 'keyword', 'per_page'])),
            FactoryResource::class,
        );
    }

    public function store(StoreFactoryRequest $request): JsonResponse
    {
        return ApiResponse::success(FactoryResource::make(
            $this->entities->create(Factory::class, $request->validated()),
        ));
    }

    public function update(UpdateFactoryRequest $request, Factory $factory): JsonResponse
    {
        return ApiResponse::success(FactoryResource::make(
            $this->entities->update($factory, $request->validated()),
        ));
    }

    public function destroy(Factory $factory): JsonResponse
    {
        $this->entities->delete($factory, [
            'workshops' => '工厂下还有车间，只能停用',
        ]);

        return ApiResponse::success();
    }
}
