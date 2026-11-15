<?php

namespace App\Modules\Inventory\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Inventory\FormRequest\AdjustInventoryRequest;
use App\Modules\Inventory\FormRequest\InventoryMovementRequest;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Inventory\Resource\InventoryResource;
use App\Modules\Inventory\Resource\InventoryTransactionResource;
use App\Modules\Inventory\Service\InventoryService;
use App\Modules\Material\Model\Material;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly EntityService $entities,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $locationId = $request->query('location_id');
        $batchNo = $request->query('batch_no');
        $keyword = $request->query('keyword');

        $query = Inventory::query()
            ->with(['material', 'location.warehouse'])
            ->when(is_scalar($warehouseId), fn (Builder $query) => $query->whereHas(
                'location',
                fn (Builder $location) => $location->where('warehouse_id', $warehouseId),
            ))
            ->when(is_scalar($locationId), fn (Builder $query) => $query->where('location_id', $locationId))
            ->when(is_string($batchNo) && $batchNo !== '', fn (Builder $query) => $query->where('batch_no', $batchNo))
            ->when(is_string($keyword) && $keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(fn (Builder $inner) => $inner
                    ->orWhere('batch_no', 'like', "%{$keyword}%")
                    ->orWhereHas('location', fn (Builder $location) => $location
                        ->where('code', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%")
                        ->orWhereHas('warehouse', fn (Builder $warehouse) => $warehouse
                            ->where('code', 'like', "%{$keyword}%")
                            ->orWhere('name', 'like', "%{$keyword}%")))
                    ->orWhereHas('material', fn (Builder $material) => $material
                        ->where('code', 'like', "%{$keyword}%")
                        ->orWhere('name', 'like', "%{$keyword}%")));
            });

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['material_id', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                [],
                filterColumns: ['material_id'],
            ),
            InventoryResource::class,
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $locationId = $request->query('location_id');
        $batchNo = $request->query('batch_no');

        $query = InventoryTransaction::query()
            ->with(['material', 'createdBy', 'inventory.location.warehouse'])
            ->when(is_scalar($warehouseId), fn (Builder $query) => $query->whereHas(
                'inventory.location',
                fn (Builder $location) => $location->where('warehouse_id', $warehouseId),
            ))
            ->when(is_scalar($locationId), fn (Builder $query) => $query->whereHas(
                'inventory',
                fn (Builder $inventory) => $inventory->where('location_id', $locationId),
            ))
            ->when(is_string($batchNo) && $batchNo !== '', fn (Builder $query) => $query->whereHas(
                'inventory',
                fn (Builder $inventory) => $inventory->where('batch_no', $batchNo),
            ));

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
                array_filter(
                    $request->only(['inventory_id', 'material_id', 'business_type', 'reference_no', 'per_page']),
                    static fn (mixed $value): bool => is_scalar($value),
                ),
                [],
                filterColumns: ['inventory_id', 'material_id', 'business_type', 'reference_no'],
            ),
            InventoryTransactionResource::class,
        );
    }

    public function options(Request $request): JsonResponse
    {
        // shortcut: one page covers a demo factory's master data; add paging when it grows.
        $materials = Material::query()
            ->where('status', true)
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'code', 'name', 'unit', 'batch_managed'])
            ->map(fn (Material $material) => [
                'id' => $material->id,
                'code' => $material->code,
                'name' => $material->name,
                'unit' => $material->unit,
                'batch_managed' => $material->batch_managed,
            ]);

        $warehouses = Warehouse::query()
            ->with('locations')
            ->where('status', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Warehouse $warehouse) => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'locations' => $warehouse->locations
                    ->where('status', true)
                    ->sortBy('code')
                    ->values()
                    ->map(fn (StorageLocation $location) => [
                        'id' => $location->id,
                        'code' => $location->code,
                        'name' => $location->name,
                    ])
                    ->all(),
            ]);

        return ApiResponse::success([
            'materials' => $materials,
            'warehouses' => $warehouses,
        ]);
    }

    public function inbound(InventoryMovementRequest $request): JsonResponse
    {
        return ApiResponse::success(InventoryResource::make(
            $this->inventory->inbound($request->validated(), $request->user()?->id)->load(['material', 'location.warehouse']),
        ));
    }

    public function outbound(InventoryMovementRequest $request): JsonResponse
    {
        return ApiResponse::success(InventoryResource::make(
            $this->inventory->outbound($request->validated(), $request->user()?->id)->load(['material', 'location.warehouse']),
        ));
    }

    public function adjust(AdjustInventoryRequest $request): JsonResponse
    {
        return ApiResponse::success(InventoryResource::make(
            $this->inventory->adjust($request->validated(), $request->user()?->id)->load(['material', 'location.warehouse']),
        ));
    }
}
