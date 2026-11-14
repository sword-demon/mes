<?php

namespace App\Modules\Product\Controller;

use App\Core\Services\EntityService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Product\FormRequest\StoreProductRequest;
use App\Modules\Product\FormRequest\UpdateProductRequest;
use App\Modules\Product\Model\Product;
use App\Modules\Product\Resource\ProductResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->entities->paginate(
                Product::class,
                [],
                $request->only(['category', 'status', 'keyword', 'per_page']),
                filterColumns: ['category'],
            ),
            ProductResource::class,
        );
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        return ApiResponse::success(ProductResource::make(
            $this->entities->create(Product::class, $request->validated()),
        ));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        return ApiResponse::success(ProductResource::make(
            $this->entities->update($product, $request->validated()),
        ));
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->entities->delete($product, [
            'versions' => '产品下还有版本，只能停用',
        ]);

        return ApiResponse::success();
    }
}
