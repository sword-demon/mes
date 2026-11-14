<?php

namespace App\Modules\Product\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Product\FormRequest\StoreProductVersionRequest;
use App\Modules\Product\FormRequest\UpdateProductVersionRequest;
use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use App\Modules\Product\Resource\ProductVersionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVersionController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request, Product $product): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                ProductVersion::class,
                [],
                array_merge(
                    $request->only(['status', 'keyword', 'per_page']),
                    ['product_id' => $product->id],
                ),
                searchColumns: ['version', 'remark'],
                enabledRelations: ['product'],
                filterColumns: ['product_id'],
            ),
            ProductVersionResource::class,
        );
    }

    public function store(StoreProductVersionRequest $request, Product $product): JsonResponse
    {
        return ApiResponse::success(ProductVersionResource::make(
            $this->entities->create(ProductVersion::class, array_merge(
                $request->validated(),
                ['product_id' => $product->id],
            )),
        ));
    }

    public function update(UpdateProductVersionRequest $request, Product $product, ProductVersion $version): JsonResponse
    {
        return ApiResponse::success(ProductVersionResource::make(
            $this->entities->update($version, $request->validated()),
        ));
    }
}
