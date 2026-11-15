<?php

namespace App\Modules\Inventory\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Inventory\Model\InventoryAlert;
use App\Modules\Inventory\Resource\InventoryAlertResource;
use App\Modules\Inventory\Service\InventoryAlertService;
use App\Modules\Material\Model\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryAlertController extends Controller
{
    public function __construct(
        private readonly EntityService $entities,
        private readonly InventoryAlertService $alerts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'material_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->alerts->activeQuery()
            ->when($data['material_id'] ?? null, fn ($query, $materialId) => $query->where('material_id', $materialId));

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_intersect_key($data, ['per_page' => true]),
            ),
            InventoryAlertResource::class,
        );
    }

    public function materialOptions(Request $request): JsonResponse
    {
        $data = $request->validate(['keyword' => ['required', 'string', 'min:1', 'max:128']]);
        $keyword = $data['keyword'];
        $materials = Material::query()
            ->where('status', true)
            ->where(fn (Builder $query) => $query
                ->where('code', 'like', "%{$keyword}%")
                ->orWhere('name', 'like', "%{$keyword}%"))
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'code', 'name', 'unit', 'batch_managed']);

        return ApiResponse::success(['materials' => $materials]);
    }

    public function confirm(Request $request, InventoryAlert $alert): JsonResponse
    {
        return ApiResponse::success(InventoryAlertResource::make(
            $this->alerts->confirm($alert, $request->user()),
        ));
    }

    public function close(Request $request, InventoryAlert $alert): JsonResponse
    {
        return ApiResponse::success(InventoryAlertResource::make(
            $this->alerts->close($alert, $request->user()),
        ));
    }
}
