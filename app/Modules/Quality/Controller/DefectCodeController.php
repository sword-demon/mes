<?php

namespace App\Modules\Quality\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Quality\FormRequest\StoreDefectCodeRequest;
use App\Modules\Quality\FormRequest\UpdateDefectCodeRequest;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Resource\DefectCodeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DefectCodeController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->entities->paginate(
            DefectCode::class, [],
            array_filter($request->only(['keyword', 'category', 'status', 'per_page']), static fn (mixed $value): bool => is_scalar($value)),
            filterColumns: ['category'],
        ), DefectCodeResource::class);
    }

    public function store(StoreDefectCodeRequest $request): JsonResponse
    {
        return ApiResponse::success(DefectCodeResource::make($this->entities->create(DefectCode::class, $request->validated())));
    }

    public function update(UpdateDefectCodeRequest $request, DefectCode $defect): JsonResponse
    {
        return ApiResponse::success(DefectCodeResource::make($this->entities->update($defect, $request->validated())));
    }
}
