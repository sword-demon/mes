<?php

namespace App\Modules\Bom\Controller;

use App\Core\Exceptions\ApiException;
use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Bom\FormRequest\StoreBomRequest;
use App\Modules\Bom\FormRequest\UpdateBomRequest;
use App\Modules\Bom\Model\Bom;
use App\Modules\Bom\Resource\BomResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BomController extends Controller
{
    /**
     * Columns a locked BOM may still change; status disables it for new orders.
     */
    private const array FIELDS_ALLOWED_WHEN_LOCKED = ['status'];

    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                Bom::class,
                ['productVersion.product'],
                $request->only(['product_version_id', 'audit_status', 'status', 'keyword', 'per_page']),
                ['code', 'version'],
                enabledRelations: ['productVersion', 'productVersion.product'],
                filterColumns: ['product_version_id', 'audit_status'],
            ),
            BomResource::class,
        );
    }

    public function store(StoreBomRequest $request): JsonResponse
    {
        return ApiResponse::success(BomResource::make(
            $this->entities->create(Bom::class, $request->validated())
                ->load('productVersion.product'),
        ));
    }

    public function update(UpdateBomRequest $request, Bom $bom): JsonResponse
    {
        $data = $request->validated();

        if (! $bom->isEditable() && array_diff(array_keys($data), self::FIELDS_ALLOWED_WHEN_LOCKED) !== []) {
            throw new ApiException('BOM 已提交审核，不可修改，请新建版本', 403, status: 403);
        }

        return ApiResponse::success(BomResource::make(
            $this->entities->update($bom, $data)->load('productVersion.product'),
        ));
    }

    public function submit(Bom $bom): JsonResponse
    {
        if ($bom->audit_status !== Bom::DRAFT) {
            throw new ApiException('只有草稿状态的 BOM 才能提交审核', 422);
        }

        if (! $bom->items()->exists()) {
            throw new ApiException('BOM 至少需要一个子项才能提交审核', 422);
        }

        return ApiResponse::success(BomResource::make(
            $this->entities->update($bom, ['audit_status' => Bom::PENDING])
                ->load('productVersion.product'),
        ));
    }

    public function approve(Bom $bom): JsonResponse
    {
        if ($bom->audit_status !== Bom::PENDING) {
            throw new ApiException('只有待审核的 BOM 才能审核通过', 422);
        }

        return ApiResponse::success(BomResource::make(
            $this->entities->update($bom, ['audit_status' => Bom::APPROVED, 'audited_at' => now()])
                ->load('productVersion.product'),
        ));
    }
}
