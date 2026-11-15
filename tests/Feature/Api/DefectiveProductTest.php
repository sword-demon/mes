<?php

use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\System\Model\User;

/**
 * An in-progress order with one operation, one batch and an enabled defect code.
 *
 * @return array{0: ProductionOrder, 1: RouteOperation, 2: ProductionBatch, 3: DefectCode}
 */
function defectCase(): array
{
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::IN_PROGRESS]);
    $operation = RouteOperation::factory()->create([
        'route_id' => $order->route_id,
        'process_id' => Process::factory()->create()->id,
        'sequence' => 1,
    ]);
    $batch = ProductionBatch::factory()->create([
        'production_order_id' => $order->id,
        'quantity' => 10,
    ]);
    $defectCode = DefectCode::factory()->create(['status' => true]);

    return [$order, $operation, $batch, $defectCode];
}

function defectActor(): User
{
    return userWithPermissions([
        'quality.defects.index',
        'quality.defects.create',
        'quality.defects.process',
        'quality.defects.rework',
        'quality.defects.scrap',
        'quality.defects.recheck',
    ]);
}

/**
 * @param  array<string, mixed>  $data
 */
function registerDefect(User $actor, array $data): int
{
    return (int) bearer($actor)->postJson('/api/v1/quality/defective-products', $data)
        ->assertOk()
        ->json('data.id');
}

/** @return array<string, mixed> */
function defectPayload(ProductionOrder $order, RouteOperation $operation, ProductionBatch $batch, DefectCode $code): array
{
    return [
        'production_order_id' => $order->id,
        'route_operation_id' => $operation->id,
        'production_batch_id' => $batch->id,
        'defect_code_id' => $code->id,
        'quantity' => 3,
        'remark' => '首件外观异常',
    ];
}

it('registers a defect against its order, operation, batch and defect code', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();

    bearer($actor)->postJson('/api/v1/quality/defective-products', [
        ...defectPayload($order, $operation, $batch, $code),
    ])->assertOk()
        ->assertJsonPath('data.status', DefectiveProduct::PENDING)
        ->assertJsonPath('data.status_label', '待处理')
        ->assertJsonPath('data.quantity', '3.000')
        ->assertJsonPath('data.production_order.code', $order->code)
        ->assertJsonPath('data.route_operation.id', $operation->id)
        ->assertJsonPath('data.production_batch.code', $batch->code)
        ->assertJsonPath('data.defect_code.code', $code->code)
        ->assertJsonPath('data.remark', '首件外观异常')
        ->assertJsonPath('data.rework_orders', []);

    expect(DefectiveProduct::query()->sole()->defect_code_id)->toBe($code->id);
});

it('rejects a defect whose operation or batch belongs to another order', function () {
    [$order, , , $code] = defectCase();
    $ownOperation = RouteOperation::factory()->create(['route_id' => $order->route_id, 'sequence' => 2]);
    $ownBatch = ProductionBatch::factory()->create(['production_order_id' => $order->id, 'quantity' => 10]);
    [, $otherOperation, $otherBatch] = defectCase();
    $actor = defectActor();
    $url = '/api/v1/quality/defective-products';

    bearer($actor)->postJson($url, defectPayload($order, $otherOperation, $ownBatch, $code))
        ->assertUnprocessable()
        ->assertJsonPath('message', '工序不属于该生产订单');

    bearer($actor)->postJson($url, defectPayload($order, $ownOperation, $otherBatch, $code))
        ->assertUnprocessable()
        ->assertJsonPath('message', '批次不属于该生产订单');

    bearer($actor)->postJson($url, defectPayload($order, $ownOperation, $ownBatch, $code))
        ->assertOk();
});

it('rejects a disabled defect code and a non-positive quantity', function () {
    [$order, $operation, $batch] = defectCase();
    $disabled = DefectCode::factory()->create(['status' => false]);
    $actor = defectActor();
    $url = '/api/v1/quality/defective-products';

    bearer($actor)->postJson($url, defectPayload($order, $operation, $batch, $disabled))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('defect_code_id', 'data.errors')
        ->assertJsonPath('data.errors.defect_code_id.0', '缺陷代码不存在或已停用');

    bearer($actor)->postJson($url, [
        ...defectPayload($order, $operation, $batch, $disabled),
        'defect_code_id' => DefectCode::factory()->create()->id,
        'quantity' => 0,
    ])->assertUnprocessable()->assertJsonValidationErrors('quantity', 'data.errors');
});

it('rejects a defect quantity larger than its production batch', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();

    bearer($actor)->postJson('/api/v1/quality/defective-products', [
        ...defectPayload($order, $operation, $batch, $code),
        'quantity' => 11,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '不良数量不能大于批次数量');
});

it('returns registration options scoped to the selected order', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();

    bearer($actor)->getJson("/api/v1/quality/defective-products/options?production_order_id={$order->id}")
        ->assertOk()
        ->assertJsonPath('data.orders.0.id', $order->id)
        ->assertJsonPath('data.operations.0.id', $operation->id)
        ->assertJsonPath('data.batches.0.id', $batch->id)
        ->assertJsonPath('data.defect_codes.0.id', $code->id);

    bearer($actor)->getJson('/api/v1/quality/defective-products/options?keyword=NOT-EXISTS')
        ->assertOk()
        ->assertJsonCount(0, 'data.orders');
});

it('moves a defect from pending to processing before any disposition', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();
    $id = registerDefect($actor, defectPayload($order, $operation, $batch, $code));

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/rework", ['reason' => '重新打磨'])
        ->assertUnprocessable()
        ->assertJsonPath('message', '不良品当前状态为「待处理」，不能返工');

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', DefectiveProduct::PROCESSING)
        ->assertJsonPath('data.status_label', '处理中');
});

it('requires a reason for rework and generates a linked rework order', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();
    $id = registerDefect($actor, defectPayload($order, $operation, $batch, $code));

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/start")->assertOk();

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/rework", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason', 'data.errors');

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/rework", ['reason' => '重新打磨'])
        ->assertOk()
        ->assertJsonPath('data.status', DefectiveProduct::PENDING_RECHECK)
        ->assertJsonPath('data.status_label', '待复检')
        ->assertJsonPath('data.rework_orders.0.code', "RW-{$id}-1")
        ->assertJsonPath('data.rework_orders.0.reason', '重新打磨');

    expect(DefectiveProduct::query()->findOrFail($id)->reworkOrders()->count())->toBe(1);
});

it('closes only after a passed recheck and reopens on a failed one', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();
    $id = registerDefect($actor, defectPayload($order, $operation, $batch, $code));

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/start")->assertOk();
    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/rework", ['reason' => '重新打磨'])->assertOk();

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/recheck", ['passed' => false, 'remark' => '仍有划痕'])
        ->assertOk()
        ->assertJsonPath('data.status', DefectiveProduct::PROCESSING)
        ->assertJsonPath('data.recheck_result', 'failed');

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/start")
        ->assertUnprocessable()
        ->assertJsonPath('message', '不良品当前状态为「处理中」，不能开始处理');

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/rework", ['reason' => '再次打磨'])
        ->assertOk()
        ->assertJsonPath('data.rework_orders.1.code', "RW-{$id}-2");

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/recheck", ['passed' => true])
        ->assertOk()
        ->assertJsonPath('data.status', DefectiveProduct::CLOSED)
        ->assertJsonPath('data.status_label', '已关闭')
        ->assertJsonPath('data.recheck_result', 'passed');
});

it('cannot close without going through rework and recheck', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();
    $id = registerDefect($actor, defectPayload($order, $operation, $batch, $code));

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/start")->assertOk();
    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/rework", ['reason' => '重新打磨'])->assertOk();

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/scrap", ['reason' => '直接报废', 'quantity' => 3])
        ->assertUnprocessable()
        ->assertJsonPath('message', '不良品当前状态为「待复检」，不能报废');

    expect(DefectiveProduct::query()->findOrFail($id)->status)->toBe(DefectiveProduct::PENDING_RECHECK);
});

it('scraps with a reason and quantity, then closes directly', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();
    $id = registerDefect($actor, defectPayload($order, $operation, $batch, $code));
    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/start")->assertOk();

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/scrap", ['reason' => '无法修复', 'quantity' => 4])
        ->assertUnprocessable()
        ->assertJsonPath('message', '报废数量必须等于不良数量');

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/scrap", ['reason' => '无法修复', 'quantity' => 2])
        ->assertUnprocessable()
        ->assertJsonPath('message', '报废数量必须等于不良数量');

    bearer($actor)->postJson("/api/v1/quality/defective-products/{$id}/scrap", ['reason' => '无法修复', 'quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.status', DefectiveProduct::CLOSED)
        ->assertJsonPath('data.status_label', '已关闭')
        ->assertJsonPath('data.scrap_reason', '无法修复')
        ->assertJsonPath('data.scrap_quantity', '3.000');

    expect(DefectiveProduct::query()->findOrFail($id)->status)->toBe(DefectiveProduct::CLOSED);
});

it('filters the defect list by status and order', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $actor = defectActor();
    $pendingId = registerDefect($actor, defectPayload($order, $operation, $batch, $code));
    $closedId = registerDefect($actor, defectPayload($order, $operation, $batch, $code));
    bearer($actor)->postJson("/api/v1/quality/defective-products/{$closedId}/start")->assertOk();
    bearer($actor)->postJson("/api/v1/quality/defective-products/{$closedId}/scrap", ['reason' => '报废', 'quantity' => 3])
        ->assertOk();

    bearer($actor)->getJson("/api/v1/quality/defective-products?status=pending&production_order_id={$order->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $pendingId);
});

it('separates registration from disposition permissions', function () {
    [$order, $operation, $batch, $code] = defectCase();
    $inspector = userWithPermissions([
        'quality.defects.index',
        'quality.defects.create',
        'quality.defects.recheck',
    ], 'inspector');
    $id = registerDefect($inspector, defectPayload($order, $operation, $batch, $code));

    bearer($inspector)->postJson("/api/v1/quality/defective-products/{$id}/start")
        ->assertForbidden();

    bearer(userWithPermissions(['quality.defects.index'], 'viewer'))
        ->postJson('/api/v1/quality/defective-products', defectPayload($order, $operation, $batch, $code))
        ->assertForbidden();

    bearer(userWithPermissions([], 'nobody'))
        ->getJson('/api/v1/quality/defective-products/options')
        ->assertForbidden();
});

it('protects every defect endpoint and lets create-only users load options', function () {
    [$order, $operation, $batch, $code] = defectCase();

    $this->getJson('/api/v1/quality/defective-products')->assertUnauthorized();
    $this->getJson('/api/v1/quality/defective-products/options')->assertUnauthorized();
    $this->postJson('/api/v1/quality/defective-products', [])->assertUnauthorized();
    $this->postJson('/api/v1/quality/defective-products/1/start')->assertUnauthorized();
    $this->postJson('/api/v1/quality/defective-products/1/rework', [])->assertUnauthorized();
    $this->postJson('/api/v1/quality/defective-products/1/scrap', [])->assertUnauthorized();
    $this->postJson('/api/v1/quality/defective-products/1/recheck', [])->assertUnauthorized();

    $creator = userWithPermissions([
        'quality.defects.create',
    ], 'creator');
    bearer($creator)->getJson("/api/v1/quality/defective-products/options?production_order_id={$order->id}")
        ->assertOk()
        ->assertJsonPath('data.orders.0.id', $order->id);

    $actor = defectActor();
    $id = registerDefect($actor, defectPayload($order, $operation, $batch, $code));
    $stranger = userWithPermissions([], 'stranger');

    bearer($stranger)->getJson('/api/v1/quality/defective-products')->assertForbidden();
    bearer($stranger)->getJson('/api/v1/quality/defective-products/options')->assertForbidden();
    bearer($stranger)->postJson("/api/v1/quality/defective-products/{$id}/start")->assertForbidden();
    bearer($stranger)->postJson("/api/v1/quality/defective-products/{$id}/rework", ['reason' => '返工'])->assertForbidden();
    bearer($stranger)->postJson("/api/v1/quality/defective-products/{$id}/scrap", ['reason' => '报废', 'quantity' => 3])->assertForbidden();
    bearer($stranger)->postJson("/api/v1/quality/defective-products/{$id}/recheck", ['passed' => true])->assertForbidden();
});
