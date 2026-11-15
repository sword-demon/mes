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
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        $query = Bom::query()->with(['productVersion.product']);

        if ($request->boolean('usable')) {
            $query->usable();
        }

        return ApiResponse::paginated(
            $this->entities->paginateQuery(
                $query,
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

        $bom->ensureEditable(array_keys($data));

        return ApiResponse::success(BomResource::make(
            $this->entities->update($bom, $data)->load('productVersion.product'),
        ));
    }

    public function submit(Bom $bom): JsonResponse
    {
        $this->ensureActiveProduct($bom, '提交审核');

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
        $this->ensureActiveProduct($bom, '审核通过');

        if ($bom->audit_status !== Bom::PENDING) {
            throw new ApiException('只有待审核的 BOM 才能审核通过', 422);
        }

        return ApiResponse::success(BomResource::make(
            $this->entities->update($bom, ['audit_status' => Bom::APPROVED, 'audited_at' => now()])
                ->load('productVersion.product'),
        ));
    }

    private function ensureActiveProduct(Bom $bom, string $action): void
    {
        $bom->loadMissing('productVersion.product');

        if (! $bom->productVersion->status) {
            throw new ApiException("产品版本已停用，不能{$action}", 422);
        }

        if (! $bom->productVersion->product->status) {
            throw new ApiException("产品已停用，不能{$action}", 422);
        }
    }
}
