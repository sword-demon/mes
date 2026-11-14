<?php

namespace App\Modules\Bom\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Bom\FormRequest\StoreBomItemRequest;
use App\Modules\Bom\FormRequest\UpdateBomItemRequest;
use App\Modules\Bom\Model\Bom;
use App\Modules\Bom\Model\BomItem;
use App\Modules\Bom\Resource\BomItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BomItemController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request, Bom $bom): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                BomItem::class,
                ['material'],
                array_merge(
                    $request->only(['material_id', 'per_page']),
                    ['bom_id' => $bom->id],
                ),
                searchColumns: [],
                filterColumns: ['bom_id', 'material_id'],
            ),
            BomItemResource::class,
        );
    }

    public function store(StoreBomItemRequest $request, Bom $bom): JsonResponse
    {
        $bom->ensureEditable();

        return ApiResponse::success(BomItemResource::make(
            $this->entities->create(BomItem::class, array_merge(
                $request->validated(),
                ['bom_id' => $bom->id],
            ))->load('material'),
        ));
    }

    public function update(UpdateBomItemRequest $request, Bom $bom, BomItem $item): JsonResponse
    {
        $bom->ensureEditable();

        return ApiResponse::success(BomItemResource::make(
            $this->entities->update($item, $request->validated())->load('material'),
        ));
    }

    public function destroy(Bom $bom, BomItem $item): JsonResponse
    {
        $bom->ensureEditable();

        $this->entities->delete($item, []);

        return ApiResponse::success();
    }
}
