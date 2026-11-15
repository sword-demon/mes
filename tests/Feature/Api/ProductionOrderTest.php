<?php

use App\Modules\Bom\Model\Bom;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use App\Modules\Production\Model\ProductionOrder;

/**
 * @return array{0: Product, 1: Bom, 2: ProcessRoute, 3: ProductionLine}
 */
function usableProductionReferences(string $bomVersion = 'B1.0', string $routeVersion = 'R1.0'): array
{
    $product = Product::factory()->create();
    $version = ProductVersion::factory()->create(['product_id' => $product->id]);
    $bom = Bom::factory()->create([
        'product_version_id' => $version->id,
        'version' => $bomVersion,
        'audit_status' => Bom::APPROVED,
    ]);
    $route = ProcessRoute::factory()->create([
        'product_id' => $product->id,
        'version' => $routeVersion,
        'audit_status' => ProcessRoute::APPROVED,
    ]);
    $line = ProductionLine::factory()->create();

    return [$product, $bom, $route, $line];
}

/**
 * @param  array<int, string>  $permissions
 * @return array<string, mixed>
 */
function productionOrderPayload(Product $product, Bom $bom, ProcessRoute $route, ProductionLine $line, array $overrides = []): array
{
    return [
        'code' => 'PO-2026-001',
        'product_id' => $product->id,
        'bom_id' => $bom->id,
        'route_id' => $route->id,
        'production_line_id' => $line->id,
        'planned_quantity' => 120,
        'planned_start_at' => '2026-11-16 08:00:00',
        'planned_end_at' => '2026-11-18 18:00:00',
        'remark' => '首单',
        ...$overrides,
    ];
}

it('creates a draft order and snapshots the locked bom and route versions', function () {
    [$product, $bom, $route, $line] = usableProductionReferences('B2.0', 'R3.0');
    $actor = userWithPermissions(['production.orders.create']);

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertOk()
        ->assertJsonPath('data.code', 'PO-2026-001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.status_label', '草稿')
        ->assertJsonPath('data.product.id', $product->id)
        ->assertJsonPath('data.product_version.id', $bom->product_version_id)
        ->assertJsonPath('data.bom.id', $bom->id)
        ->assertJsonPath('data.bom_version', 'B2.0')
        ->assertJsonPath('data.route.id', $route->id)
        ->assertJsonPath('data.route_version', 'R3.0')
        ->assertJsonPath('data.production_line.id', $line->id)
        ->assertJsonPath('data.planned_quantity', '120.000')
        ->assertJsonPath('data.produced_quantity', '0.000');

    $this->assertDatabaseHas('mes_production_orders', [
        'code' => 'PO-2026-001',
        'product_id' => $product->id,
        'product_version_id' => $bom->product_version_id,
        'bom_id' => $bom->id,
        'bom_version' => 'B2.0',
        'route_version' => 'R3.0',
        'status' => ProductionOrder::DRAFT,
    ]);
});

it('rejects a bom that is not approved or not enabled', function () {
    [$product, $bom, $route, $line] = usableProductionReferences();
    $actor = userWithPermissions(['production.orders.create']);

    $bom->forceFill(['audit_status' => Bom::DRAFT])->save();

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'BOM 未审核通过，不能用于生产订单');

    $bom->forceFill(['audit_status' => Bom::APPROVED, 'status' => false])->save();

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'BOM 已停用，不能用于生产订单');
});

it('rejects a bom from another product', function () {
    [$product, , $route, $line] = usableProductionReferences();
    $otherBom = Bom::factory()->create(['version' => 'B9.0', 'audit_status' => Bom::APPROVED]);
    $actor = userWithPermissions(['production.orders.create']);

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $otherBom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'BOM 不属于所选产品');
});

it('rejects a route that is not approved, disabled, or contains a disabled process', function () {
    [$product, $bom, $route, $line] = usableProductionReferences();
    $actor = userWithPermissions(['production.orders.create']);

    $route->forceFill(['audit_status' => ProcessRoute::DRAFT])->save();

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', '工艺路线未审核通过，不能用于生产订单');

    $route->forceFill(['audit_status' => ProcessRoute::APPROVED, 'status' => false])->save();

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', '工艺路线已停用或包含停用工序，不能用于生产订单');

    $route->forceFill(['status' => true])->save();
    $process = Process::factory()->create(['status' => false]);
    RouteOperation::factory()->create(['route_id' => $route->id, 'process_id' => $process->id, 'sequence' => 1]);

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', '工艺路线已停用或包含停用工序，不能用于生产订单');
});

it('rejects a disabled product or production line and a duplicate order code', function () {
    [$product, $bom, $route, $line] = usableProductionReferences();
    $actor = userWithPermissions(['production.orders.create']);

    $line->forceFill(['status' => false])->save();

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', '产线不存在或已停用，不能用于生产订单');

    $line->forceFill(['status' => true])->save();
    $product->forceFill(['status' => false])->save();

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', '产品已停用，不能用于生产订单');

    $product->forceFill(['status' => true])->save();
    ProductionOrder::factory()->create(['code' => 'PO-2026-001']);

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertUnprocessable()
        ->assertJsonPath('message', '订单号已存在');
});

it('walks the full order state machine and rejects transitions that skip states', function () {
    [$product, $bom, $route, $line] = usableProductionReferences();
    $actor = userWithPermissions([
        'production.orders.create',
        'production.orders.submit',
        'production.orders.audit',
        'production.orders.release',
        'production.orders.start',
        'production.orders.pause',
        'production.orders.resume',
        'production.orders.complete',
        'production.orders.close',
    ]);

    $order = bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertOk()
        ->json('data');

    bearer($actor)->postJson("/api/v1/production/orders/{$order['id']}/approve")
        ->assertUnprocessable()
        ->assertJsonPath('message', '生产订单当前状态为「草稿」，不能变更为「已审核」');

    $steps = [
        'submit' => ProductionOrder::PENDING,
        'approve' => ProductionOrder::APPROVED,
        'release' => ProductionOrder::RELEASED,
        'start' => ProductionOrder::IN_PROGRESS,
        'pause' => ProductionOrder::PAUSED,
        'resume' => ProductionOrder::IN_PROGRESS,
        'complete' => ProductionOrder::COMPLETED,
        'close' => ProductionOrder::CLOSED,
    ];

    foreach ($steps as $action => $expected) {
        bearer($actor)->postJson("/api/v1/production/orders/{$order['id']}/{$action}")
            ->assertOk()
            ->assertJsonPath('data.status', $expected);
    }

    bearer($actor)->postJson("/api/v1/production/orders/{$order['id']}/start")
        ->assertUnprocessable()
        ->assertJsonPath('message', '生产订单当前状态为「已关闭」，不能变更为「生产中」');
});

it('cancels an order before completion but never after it is completed', function () {
    [$product, $bom, $route, $line] = usableProductionReferences();
    $actor = userWithPermissions(['production.orders.create', 'production.orders.cancel']);

    $order = bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->json('data');

    bearer($actor)->postJson("/api/v1/production/orders/{$order['id']}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', ProductionOrder::CANCELLED);

    bearer($actor)->postJson("/api/v1/production/orders/{$order['id']}/cancel")
        ->assertUnprocessable()
        ->assertJsonPath('message', '生产订单当前状态为「已取消」，不能变更为「已取消」');

    $completed = ProductionOrder::factory()->create(['status' => ProductionOrder::COMPLETED]);

    bearer($actor)->postJson("/api/v1/production/orders/{$completed->id}/cancel")
        ->assertUnprocessable()
        ->assertJsonPath('message', '生产订单当前状态为「已完成」，不能变更为「已取消」');
});

it('edits a draft order, re-pins the bom and route, and locks it once submitted', function () {
    [$product, $bom, $route, $line] = usableProductionReferences('B1.0', 'R1.0');
    $newBom = Bom::factory()->create([
        'product_version_id' => $bom->product_version_id,
        'version' => 'B2.0',
        'audit_status' => Bom::APPROVED,
    ]);
    $newRoute = ProcessRoute::factory()->create([
        'product_id' => $product->id,
        'version' => 'R2.0',
        'audit_status' => ProcessRoute::APPROVED,
    ]);
    $actor = userWithPermissions(['production.orders.create', 'production.orders.update', 'production.orders.submit']);

    $order = bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->json('data');

    bearer($actor)->putJson("/api/v1/production/orders/{$order['id']}", [
        'bom_id' => $newBom->id,
        'route_id' => $newRoute->id,
        'planned_quantity' => 150,
    ])->assertOk()
        ->assertJsonPath('data.bom_version', 'B2.0')
        ->assertJsonPath('data.route_version', 'R2.0')
        ->assertJsonPath('data.planned_quantity', '150.000');

    bearer($actor)->postJson("/api/v1/production/orders/{$order['id']}/submit")->assertOk();

    bearer($actor)->putJson("/api/v1/production/orders/{$order['id']}", ['remark' => '迟到的修改'])
        ->assertForbidden()
        ->assertJsonPath('message', '生产订单已提交，不可修改');
});

it('keeps the version snapshots after the source records change', function () {
    [$product, $bom, $route, $line] = usableProductionReferences('B1.0', 'R1.0');
    $actor = userWithPermissions(['production.orders.create', 'production.orders.index']);

    bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->assertOk();

    Bom::query()->whereKey($bom->id)->update(['version' => 'B9.9']);
    ProcessRoute::query()->whereKey($route->id)->update(['version' => 'R9.9']);

    bearer($actor)->getJson('/api/v1/production/orders')
        ->assertOk()
        ->assertJsonPath('data.items.0.bom_version', 'B1.0')
        ->assertJsonPath('data.items.0.route_version', 'R1.0');
});

it('still lets a draft change its remark after the pinned bom was retired', function () {
    [$product, $bom, $route, $line] = usableProductionReferences();
    $actor = userWithPermissions(['production.orders.create', 'production.orders.update']);

    $order = bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->json('data');

    $bom->forceFill(['status' => false])->save();

    bearer($actor)->putJson("/api/v1/production/orders/{$order['id']}", [
        'remark' => '仅改备注',
        'product_id' => $product->id,
        'bom_id' => $bom->id,
        'route_id' => $route->id,
        'production_line_id' => $line->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.remark', '仅改备注')
        ->assertJsonPath('data.bom_version', 'B1.0');
});

it('rejects an end date earlier than the stored start on a partial update', function () {
    [$product, $bom, $route, $line] = usableProductionReferences();
    $actor = userWithPermissions(['production.orders.create', 'production.orders.update']);

    $order = bearer($actor)->postJson('/api/v1/production/orders', productionOrderPayload($product, $bom, $route, $line))
        ->json('data');

    bearer($actor)->putJson("/api/v1/production/orders/{$order['id']}", [
        'planned_end_at' => '2026-11-15 08:00:00',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '计划结束时间不能早于计划开始时间');
});

it('filters orders by status, product, line and planned date', function () {
    [$product, $bom, $route, $line] = usableProductionReferences();
    $otherProduct = Product::factory()->create();
    $otherLine = ProductionLine::factory()->create();
    $actor = userWithPermissions(['production.orders.index']);

    ProductionOrder::factory()->create([
        'product_id' => $product->id,
        'production_line_id' => $line->id,
        'status' => ProductionOrder::RELEASED,
        'planned_start_at' => '2026-11-10 08:00:00',
    ]);
    ProductionOrder::factory()->create([
        'product_id' => $otherProduct->id,
        'production_line_id' => $otherLine->id,
        'status' => ProductionOrder::DRAFT,
        'planned_start_at' => '2026-12-10 08:00:00',
    ]);

    bearer($actor)->getJson('/api/v1/production/orders?'.http_build_query([
        'status' => ProductionOrder::RELEASED,
        'product_id' => $product->id,
        'production_line_id' => $line->id,
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.status', ProductionOrder::RELEASED);

    bearer($actor)->getJson('/api/v1/production/orders?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.product.id', $otherProduct->id);
});

it('requires the matching permission for every order endpoint', function () {
    $actor = userWithPermissions([]);
    $order = ProductionOrder::factory()->create();

    bearer($actor)->getJson('/api/v1/production/orders')
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    bearer($actor)->postJson('/api/v1/production/orders', [])
        ->assertForbidden();

    bearer($actor)->postJson("/api/v1/production/orders/{$order->id}/submit")
        ->assertForbidden();
});
