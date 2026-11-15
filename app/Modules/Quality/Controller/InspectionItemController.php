<?php

namespace App\Modules\Quality\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Quality\FormRequest\StoreInspectionItemRequest;
use App\Modules\Quality\FormRequest\UpdateInspectionItemRequest;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Resource\InspectionItemResource;
use App\Modules\Quality\Service\InspectionItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InspectionItemController extends Controller
{
    public function __construct(private readonly EntityService $entities, private readonly InspectionItemService $inspectionItems) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated($this->entities->paginate(
            InspectionItem::class, ['standard'],
            array_filter($request->only(['keyword', 'status', 'per_page']), static fn (mixed $value): bool => is_scalar($value)),
        ), InspectionItemResource::class);
    }

    public function store(StoreInspectionItemRequest $request): JsonResponse
    {
        return ApiResponse::success(InspectionItemResource::make($this->inspectionItems->save($request->validated())));
    }

    public function update(UpdateInspectionItemRequest $request, InspectionItem $item): JsonResponse
    {
        return ApiResponse::success(InspectionItemResource::make($this->inspectionItems->save($request->validated(), $item)));
    }
}
