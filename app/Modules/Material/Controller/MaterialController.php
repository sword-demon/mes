<?php

namespace App\Modules\Material\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Material\FormRequest\StoreMaterialRequest;
use App\Modules\Material\FormRequest\UpdateMaterialRequest;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Resource\MaterialResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                Material::class,
                [],
                $request->only(['type', 'status', 'keyword', 'per_page']),
                filterColumns: ['type'],
            ),
            MaterialResource::class,
        );
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        return ApiResponse::success(MaterialResource::make(
            $this->entities->create(Material::class, $request->validated()),
        ));
    }

    public function update(UpdateMaterialRequest $request, Material $material): JsonResponse
    {
        return ApiResponse::success(MaterialResource::make(
            $this->entities->update($material, $request->validated()),
        ));
    }
}
