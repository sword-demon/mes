<?php

use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Product\Model\Product;
use Illuminate\Database\QueryException;

it('creates a draft route for a product and allows several versions', function () {
    $actor = userWithPermissions(['process.routes.create']);
    $product = Product::factory()->create(['code' => 'P001', 'name' => '智能电表']);

    bearer($actor)->postJson('/api/v1/process/routes', [
        'code' => 'RT-P001-01',
        'product_id' => $product->id,
        'version' => 'V1.0',
        'remark' => '标准路线',
    ])->assertOk()
        ->assertJsonPath('data.code', 'RT-P001-01')
        ->assertJsonPath('data.version', 'V1.0')
        ->assertJsonPath('data.audit_status', 'draft')
        ->assertJsonPath('data.status', true)
        ->assertJsonPath('data.audited_at', null)
        ->assertJsonPath('data.product.code', 'P001');

    bearer($actor)->postJson('/api/v1/process/routes', [
        'code' => 'RT-P001-02',
        'product_id' => $product->id,
        'version' => 'V2.0',
    ])->assertOk();

    bearer($actor)->postJson('/api/v1/process/routes', [
        'code' => 'RT-P001-03',
        'product_id' => $product->id,
        'version' => 'V2.0',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '版本号已存在');

    expect(ProcessRoute::query()->where('product_id', $product->id)->count())->toBe(2);
});

it('enforces one route version per product in the database', function () {
    $product = Product::factory()->create();
    ProcessRoute::factory()->create(['product_id' => $product->id, 'version' => 'V1.0']);

    expect(fn () => ProcessRoute::factory()->create(['product_id' => $product->id, 'version' => 'V1.0']))
        ->toThrow(QueryException::class);
});

it('adds route operations and lists them in execution order with their process', function () {
    $actor = userWithPermissions(['process.routes.create', 'process.routes.index']);
    $route = ProcessRoute::factory()->create();
    $assembly = Process::factory()->create(['code' => 'OP005', 'name' => '组装']);
    $packing = Process::factory()->create(['code' => 'OP008', 'name' => '包装入库', 'requires_inspection' => false]);

    foreach ([[$packing, 2, 0.3], [$assembly, 1, 1.2]] as [$process, $sequence, $hours]) {
        bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/operations", [
            'process_id' => $process->id,
            'sequence' => $sequence,
            'standard_hours' => $hours,
            'parameters' => $sequence === 1 ? null : '贴标并装箱',
        ])->assertOk()
            ->assertJsonPath('data.sequence', $sequence)
            ->assertJsonPath('data.process.code', $process->code);
    }

    bearer($actor)->getJson("/api/v1/process/routes/{$route->id}/operations")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.sequence', 1)
        ->assertJsonPath('data.0.process.name', '组装')
        ->assertJsonPath('data.1.sequence', 2)
        ->assertJsonPath('data.1.parameters', '贴标并装箱');
});

it('rejects a duplicate sequence and a disabled process on a route', function () {
    $actor = userWithPermissions(['process.routes.create']);
    $route = ProcessRoute::factory()->create();
    $process = Process::factory()->create();
    $disabled = Process::factory()->create(['status' => false]);
    RouteOperation::factory()->create(['route_id' => $route->id, 'sequence' => 1]);

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/operations", [
        'process_id' => $process->id,
        'sequence' => 1,
    ])->assertUnprocessable()
        ->assertJsonPath('data.errors.sequence.0', '工序顺序已存在');

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/operations", [
        'process_id' => $disabled->id,
        'sequence' => 2,
    ])->assertUnprocessable()
        ->assertJsonPath('data.errors.process_id.0', '工序不存在或已停用');
});

it('updates and deletes a route operation', function () {
    $actor = userWithPermissions(['process.routes.update']);
    $route = ProcessRoute::factory()->create();
    $operation = RouteOperation::factory()->create(['route_id' => $route->id, 'sequence' => 1]);

    bearer($actor)->putJson("/api/v1/process/routes/{$route->id}/operations/{$operation->id}", [
        'sequence' => 3,
        'standard_hours' => 2,
    ])->assertOk()
        ->assertJsonPath('data.sequence', 3)
        ->assertJsonPath('data.standard_hours', 2);

    bearer($actor)->deleteJson("/api/v1/process/routes/{$route->id}/operations/{$operation->id}")
        ->assertOk();

    expect(RouteOperation::query()->whereKey($operation->id)->exists())->toBeFalse();
});

it('keeps an operation reachable only through its own route', function () {
    $actor = userWithPermissions(['process.routes.update']);
    $route = ProcessRoute::factory()->create();
    $other = ProcessRoute::factory()->create();
    $operation = RouteOperation::factory()->create(['route_id' => $route->id]);

    bearer($actor)->putJson("/api/v1/process/routes/{$other->id}/operations/{$operation->id}", [
        'sequence' => 9,
    ])->assertNotFound();
});

it('requires an operation before submitting a route for audit', function () {
    $actor = userWithPermissions(['process.routes.submit']);
    $route = ProcessRoute::factory()->create();

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('message', '工艺路线至少需要一道工序才能提交审核');
});

it('moves a route from draft to pending and locks its operations', function () {
    $actor = userWithPermissions(['process.routes.submit', 'process.routes.create']);
    $route = ProcessRoute::factory()->create();
    RouteOperation::factory()->create(['route_id' => $route->id]);

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.audit_status', 'pending');

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/operations", [
        'process_id' => Process::factory()->create()->id,
        'sequence' => 2,
    ])->assertForbidden()
        ->assertJsonPath('message', '工艺路线已提交审核，不可修改，请新建版本');
});

it('approves a pending route and stamps the audit time', function () {
    $actor = userWithPermissions(['process.routes.audit']);
    $route = ProcessRoute::factory()->create(['audit_status' => ProcessRoute::PENDING]);

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.audit_status', 'approved');

    expect($route->refresh()->audited_at)->not->toBeNull();
});

it('refuses to audit a route whose product is disabled', function () {
    $actor = userWithPermissions(['process.routes.audit']);
    $product = Product::factory()->create(['status' => false]);
    $route = ProcessRoute::factory()->create([
        'product_id' => $product->id,
        'audit_status' => ProcessRoute::PENDING,
    ]);

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/approve")
        ->assertUnprocessable()
        ->assertJsonPath('message', '产品已停用，不能审核通过');

    expect($route->refresh()->audit_status)->toBe(ProcessRoute::PENDING);
});

it('only surfaces approved and enabled routes of an enabled product as usable', function () {
    $actor = userWithPermissions(['process.routes.index']);
    $product = Product::factory()->create();
    $usable = ProcessRoute::factory()->create([
        'product_id' => $product->id,
        'version' => 'V1.0',
        'audit_status' => ProcessRoute::APPROVED,
    ]);
    ProcessRoute::factory()->create([
        'product_id' => $product->id,
        'version' => 'V2.0',
        'audit_status' => ProcessRoute::PENDING,
    ]);
    ProcessRoute::factory()->create([
        'product_id' => $product->id,
        'version' => 'V3.0',
        'audit_status' => ProcessRoute::APPROVED,
        'status' => false,
    ]);

    $retiredProcess = ProcessRoute::factory()->create([
        'product_id' => $product->id,
        'version' => 'V4.0',
        'audit_status' => ProcessRoute::APPROVED,
    ]);
    $operation = RouteOperation::factory()->create(['route_id' => $retiredProcess->id]);
    $operation->process()->update(['status' => false]);

    $disabledProduct = Product::factory()->create(['status' => false]);
    ProcessRoute::factory()->create([
        'product_id' => $disabledProduct->id,
        'audit_status' => ProcessRoute::APPROVED,
    ]);

    bearer($actor)->getJson('/api/v1/process/routes?'.http_build_query([
        'product_id' => $product->id,
        'audit_status' => 'approved',
        'status' => '1',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $usable->id);

    bearer($actor)->getJson('/api/v1/process/routes?'.http_build_query([
        'audit_status' => 'approved',
        'status' => '1',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $usable->id);
});

it('refuses to submit a route that contains a retired process', function () {
    $actor = userWithPermissions(['process.routes.submit']);
    $route = ProcessRoute::factory()->create();
    $operation = RouteOperation::factory()->create(['route_id' => $route->id]);
    $operation->process()->update(['status' => false]);

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('message', '工艺路线包含已停用的工序，不能提交审核');
});

it('rejects submitting a route that is no longer a draft', function () {
    $actor = userWithPermissions(['process.routes.submit']);
    $route = ProcessRoute::factory()->create(['audit_status' => ProcessRoute::PENDING]);
    RouteOperation::factory()->create(['route_id' => $route->id]);

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有草稿状态的工艺路线才能提交审核');
});

it('rejects approving a route that is not pending', function () {
    $actor = userWithPermissions(['process.routes.audit']);
    $route = ProcessRoute::factory()->create(['audit_status' => ProcessRoute::DRAFT]);

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/approve")
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有待审核的工艺路线才能审核通过');
});

it('allows disabling a locked route but not editing its content', function () {
    $actor = userWithPermissions(['process.routes.update']);
    $route = ProcessRoute::factory()->create(['audit_status' => ProcessRoute::PENDING]);

    bearer($actor)->putJson("/api/v1/process/routes/{$route->id}", ['remark' => '改备注'])
        ->assertForbidden()
        ->assertJsonPath('message', '工艺路线已提交审核，不可修改，请新建版本');

    bearer($actor)->putJson("/api/v1/process/routes/{$route->id}", ['status' => false])
        ->assertOk()
        ->assertJsonPath('data.status', false);
});

it('keeps its own code and version when a route is updated', function () {
    $actor = userWithPermissions(['process.routes.update']);
    $product = Product::factory()->create();
    $route = ProcessRoute::factory()->create([
        'code' => 'RT-P001-01',
        'product_id' => $product->id,
        'version' => 'V1.0',
    ]);

    bearer($actor)->putJson("/api/v1/process/routes/{$route->id}", [
        'code' => 'RT-P001-01',
        'version' => 'V1.0',
        'remark' => '补充说明',
    ])->assertOk()
        ->assertJsonPath('data.remark', '补充说明');
});

it('rejects updating a route to a version its product already uses', function () {
    $actor = userWithPermissions(['process.routes.update']);
    $product = Product::factory()->create();
    $route = ProcessRoute::factory()->create(['product_id' => $product->id, 'version' => 'V1.0']);
    ProcessRoute::factory()->create(['product_id' => $product->id, 'version' => 'V2.0']);

    bearer($actor)->putJson("/api/v1/process/routes/{$route->id}", ['version' => 'V2.0'])
        ->assertUnprocessable()
        ->assertJsonPath('message', '版本号已存在');
});

it('rejects every route endpoint without its permission', function () {
    $actor = userWithPermissions([]);
    $route = ProcessRoute::factory()->create();
    $operation = RouteOperation::factory()->create(['route_id' => $route->id]);
    $process = Process::factory()->create();

    $requests = [
        ['GET', '/api/v1/process/routes', []],
        ['POST', '/api/v1/process/routes', ['code' => 'RT-X', 'product_id' => $route->product_id, 'version' => 'V9.0']],
        ['PUT', "/api/v1/process/routes/{$route->id}", ['remark' => 'x']],
        ['POST', "/api/v1/process/routes/{$route->id}/submit", []],
        ['POST', "/api/v1/process/routes/{$route->id}/approve", []],
        ['GET', "/api/v1/process/routes/{$route->id}/operations", []],
        ['POST', "/api/v1/process/routes/{$route->id}/operations", ['process_id' => $process->id, 'sequence' => 2]],
        ['PUT', "/api/v1/process/routes/{$route->id}/operations/{$operation->id}", ['sequence' => 3]],
        ['DELETE', "/api/v1/process/routes/{$route->id}/operations/{$operation->id}", []],
    ];

    foreach ($requests as [$method, $url, $payload]) {
        bearer($actor)->json($method, $url, $payload)->assertForbidden();
    }
});

it('refuses to submit a disabled route', function () {
    $actor = userWithPermissions(['process.routes.submit']);
    $route = ProcessRoute::factory()->create(['status' => false]);
    RouteOperation::factory()->create(['route_id' => $route->id]);

    bearer($actor)->postJson("/api/v1/process/routes/{$route->id}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('message', '工艺路线已停用，不能提交审核');
});
