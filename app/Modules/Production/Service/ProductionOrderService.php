<?php

namespace App\Modules\Production\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Bom\Model\Bom;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Product\Model\Product;
use App\Modules\Production\Model\ProductionOrder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductionOrderService
{
    /**
     * Pinning the BOM and route inside the same transaction keeps an order
     * from pointing at a version that changed between validation and insert.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProductionOrder
    {
        return DB::transaction(function () use ($data): ProductionOrder {
            $product = $this->activeProduct((int) $data['product_id']);
            $bom = $this->usableBom((int) $data['bom_id'], $product);
            $route = $this->usableRoute((int) $data['route_id'], $product);
            $this->ensureActiveLine((int) $data['production_line_id']);

            return ProductionOrder::query()->create([
                ...$data,
                'product_version_id' => $bom->product_version_id,
                'bom_version' => $bom->version,
                'route_version' => $route->version,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ProductionOrder $order, array $data): ProductionOrder
    {
        return DB::transaction(function () use ($order, $data): ProductionOrder {
            $order->ensureEditable();

            $effective = [...Arr::only($order->getAttributes(), [
                'product_id',
                'bom_id',
                'route_id',
                'production_line_id',
            ]), ...Arr::only($data, [
                'product_id',
                'bom_id',
                'route_id',
                'production_line_id',
            ])];

            $product = $this->activeProduct((int) $effective['product_id']);
            $bom = $this->usableBom((int) $effective['bom_id'], $product);
            $route = $this->usableRoute((int) $effective['route_id'], $product);
            $this->ensureActiveLine((int) $effective['production_line_id']);

            $order->update([
                ...$data,
                'product_version_id' => $bom->product_version_id,
                'bom_version' => $bom->version,
                'route_version' => $route->version,
            ]);

            return $order;
        });
    }

    /**
     * @throws ApiException
     */
    public function transition(ProductionOrder $order, string $target, string $action): ProductionOrder
    {
        return DB::transaction(function () use ($order, $target, $action): ProductionOrder {
            $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->transitionTo($target, $action);

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    private function activeProduct(int $productId): Product
    {
        $product = Product::query()->find($productId);

        if ($product === null) {
            throw new ApiException('产品不存在', 422);
        }

        if (! $product->status) {
            throw new ApiException('产品已停用，不能用于生产订单', 422);
        }

        return $product;
    }

    /**
     * @throws ApiException
     */
    private function usableBom(int $bomId, Product $product): Bom
    {
        $bom = Bom::query()->with('productVersion.product')->find($bomId);

        if ($bom === null) {
            throw new ApiException('BOM 不存在', 422);
        }

        if ($bom->product_id !== $product->id) {
            throw new ApiException('BOM 不属于所选产品', 422);
        }

        if ($bom->audit_status !== Bom::APPROVED) {
            throw new ApiException('BOM 未审核通过，不能用于生产订单', 422);
        }

        if (! $bom->status) {
            throw new ApiException('BOM 已停用，不能用于生产订单', 422);
        }

        if (! $bom->productVersion->status) {
            throw new ApiException('BOM 的产品版本已停用，不能用于生产订单', 422);
        }

        return $bom;
    }

    /**
     * @throws ApiException
     */
    private function usableRoute(int $routeId, Product $product): ProcessRoute
    {
        $route = ProcessRoute::query()->find($routeId);

        if ($route === null) {
            throw new ApiException('工艺路线不存在', 422);
        }

        if ($route->product_id !== $product->id) {
            throw new ApiException('工艺路线不属于所选产品', 422);
        }

        if ($route->audit_status !== ProcessRoute::APPROVED) {
            throw new ApiException('工艺路线未审核通过，不能用于生产订单', 422);
        }

        if (! ProcessRoute::query()->usable()->whereKey($routeId)->exists()) {
            throw new ApiException('工艺路线已停用或包含停用工序，不能用于生产订单', 422);
        }

        return $route;
    }

    /**
     * @throws ApiException
     */
    private function ensureActiveLine(int $lineId): void
    {
        if (! ProductionLine::query()->whereKey($lineId)->where('status', true)->exists()) {
            throw new ApiException('产线不存在或已停用，不能用于生产订单', 422);
        }
    }
}
