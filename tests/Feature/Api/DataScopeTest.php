<?php

use App\Core\Exceptions\ApiException;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Model\Workshop;
use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Service\ProcessRecordService;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;

/**
 * A factory plus a second factory, a line-scoped user bound to the first
 * line of the first factory, and a factory-scoped user of that factory.
 *
 * @return array{0: Factory, 1: ProductionLine, 2: ProductionLine, 3: ProductionLine, 4: User, 5: User}
 */
function productionScopeFixture(): array
{
    [$factoryA, $lineA1, $lineA2] = factoryWithLines();
    $factoryB = Factory::factory()->create();
    $workshopB = Workshop::factory()->create(['factory_id' => $factoryB->id]);
    $lineB = ProductionLine::factory()->create(['workshop_id' => $workshopB->id]);

    $lineUser = userWithPermissions([
        'data.scope.line',
        'production.orders.index',
        'production.orders.create',
        'production.orders.update',
        'production.tasks.index',
        'production.tasks.create',
        'production.tasks.update',
        'production.tasks.pause',
        'production.dispatches.index',
        'production.dispatches.create',
        'production.dispatches.mine',
        'production.reports.index',
        'production.reports.create',
        'production.flows.index',
        'production.flows.start',
        'production.flows.complete',
        'production.flows.submit',
        'production.flows.receive',
        'production.flows.exception',
        'production.flows.rework',
    ], 'scope_director_a');
    $lineUser->update(['factory_id' => $factoryA->id]);
    $lineUser->productionLines()->sync([$lineA1->id]);

    $factoryUser = userWithPermissions([
        'data.scope.factory',
        'production.orders.index',
        'production.tasks.index',
    ], 'scope_factory_admin');
    $factoryUser->update(['factory_id' => $factoryA->id]);

    return [$factoryA, $lineA1, $lineA2, $lineB, $lineUser, $factoryUser];
}

it('returns the line scope with the bound production lines', function () {
    [$factory, $first, $second] = factoryWithLines();
    $user = userWithPermissions(['data.scope.line'], 'workshop_director');
    $user->update(['factory_id' => $factory->id]);
    $user->productionLines()->sync([$second->id, $first->id]);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'line')
        ->assertJsonPath('data.data_scope_detail.level', 'line')
        ->assertJsonPath('data.data_scope_detail.factory_id', $factory->id)
        ->assertJsonPath('data.data_scope_detail.production_line_ids', [$first->id, $second->id]);
});

it('returns the factory scope without production lines', function () {
    [$factory] = factoryWithLines();
    $user = userWithPermissions(['data.scope.factory'], 'factory_admin');
    $user->update(['factory_id' => $factory->id]);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'factory')
        ->assertJsonPath('data.data_scope_detail.level', 'factory')
        ->assertJsonPath('data.data_scope_detail.factory_id', $factory->id)
        ->assertJsonPath('data.data_scope_detail.production_line_ids', []);
});

it('returns the all scope for a super admin', function () {
    bearer(superAdmin())->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'all')
        ->assertJsonPath('data.data_scope_detail.level', 'all')
        ->assertJsonPath('data.data_scope_detail.factory_id', null)
        ->assertJsonPath('data.data_scope_detail.production_line_ids', []);
});

it('returns the all scope for a non-super-admin holding the all data permission', function () {
    $user = userWithPermissions(['data.scope.all'], 'auditor');

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'all')
        ->assertJsonPath('data.data_scope_detail.level', 'all');
});

it('keeps the granted scope but resolves none when a factory-scoped user has no factory', function () {
    $user = userWithPermissions(['data.scope.factory'], 'factory_admin');

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'factory')
        ->assertJsonPath('data.data_scope_detail.level', 'none');
});

it('keeps the granted scope but resolves none when a line-scoped user has no production line', function () {
    [$factory] = factoryWithLines();
    $user = userWithPermissions(['data.scope.line'], 'workshop_director');
    $user->update(['factory_id' => $factory->id]);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'line')
        ->assertJsonPath('data.data_scope_detail.level', 'none');
});

it('returns none when a user holds no data scope permission', function () {
    // Built by hand: the shared helper appends data.scope.all to lists that
    // carry no data.scope grant, which is exactly the case under test here.
    $role = Role::factory()->create(['code' => 'operator_without_scope']);
    $role->permissions()->attach(Permission::query()->firstOrCreate(
        ['code' => 'production.orders.index'],
        ['name' => 'production.orders.index', 'type' => 'api'],
    ));
    $user = User::factory()->create();
    $user->roles()->attach($role);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'none')
        ->assertJsonPath('data.data_scope_detail.level', 'none');
});

it('stores the factory and production lines through the user admin api', function () {
    [$factory, $first, $second] = factoryWithLines();

    $userId = bearer(superAdmin())->postJson('/api/v1/system/users', [
        'name' => '张三',
        'email' => 'zhang@mes.test',
        'password' => 'password-123',
        'factory_id' => $factory->id,
        'production_line_ids' => [$first->id, $second->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.factory_id', $factory->id)
        ->assertJsonCount(2, 'data.production_lines')
        ->json('data.id');

    $user = User::query()->findOrFail($userId);

    expect($user->productionLines()->orderBy('mes_production_lines.id')->pluck('mes_production_lines.id')->all())
        ->toBe([$first->id, $second->id]);
});

it('replaces the production lines when the user is updated', function () {
    [$factory, $first, $second] = factoryWithLines();
    $user = User::factory()->create(['factory_id' => $factory->id]);
    $user->productionLines()->sync([$first->id]);

    bearer(superAdmin())->putJson("/api/v1/system/users/{$user->id}", [
        'production_line_ids' => [$second->id],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.production_lines')
        ->assertJsonPath('data.production_lines.0.id', $second->id);

    expect($user->fresh()->productionLines()->pluck('mes_production_lines.id')->all())->toBe([$second->id]);
});

it('scopes the order list to the bound production line', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    createScopedOrder($lineA1, 'PO-A-001');
    createScopedOrder($lineB, 'PO-B-001');

    bearer($lineUser)->getJson('/api/v1/production/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', 'PO-A-001');
});

it('scopes the order list to the whole factory', function () {
    [, $lineA1, $lineA2, $lineB, , $factoryUser] = productionScopeFixture();
    createScopedOrder($lineA1, 'PO-A-001');
    createScopedOrder($lineA2, 'PO-A-002');
    createScopedOrder($lineB, 'PO-B-001');

    $codes = collect(bearer($factoryUser)->getJson('/api/v1/production/orders')
        ->assertOk()
        ->json('data.items'))->pluck('code')->values()->all();

    expect($codes)->toBe(['PO-A-001', 'PO-A-002']);
});

it('shows unassigned tasks through the line of their order', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $orderA = createScopedOrder($lineA1, 'PO-A-001');
    $orderB = createScopedOrder($lineB, 'PO-B-001');
    ProductionTask::factory()->create(['code' => 'PO-A-001-T01', 'production_order_id' => $orderA->id]);
    ProductionTask::factory()->create(['code' => 'PO-B-001-T01', 'production_order_id' => $orderB->id]);

    $codes = collect(bearer($lineUser)->getJson('/api/v1/production/tasks')
        ->assertOk()
        ->json('data.items'))->pluck('code')->values()->all();

    expect($codes)->toBe(['PO-A-001-T01']);
});

it('hides a task placed on another line of the same factory', function () {
    [, $lineA1, $lineA2, , $lineUser] = productionScopeFixture();
    $orderA = createScopedOrder($lineA1, 'PO-A-001');
    ProductionTask::factory()->create([
        'code' => 'PO-A-001-T01',
        'production_order_id' => $orderA->id,
        'production_line_id' => $lineA2->id,
    ]);

    bearer($lineUser)->getJson('/api/v1/production/tasks')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

it('rejects creating an order on a line outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    [$product, $bom, $route] = usableProductionRefs();

    bearer($lineUser)->postJson('/api/v1/production/orders', [
        'code' => 'PO-OUT-001',
        'product_id' => $product->id,
        'bom_id' => $bom->id,
        'route_id' => $route->id,
        'production_line_id' => $lineB->id,
        'planned_quantity' => 10,
        'planned_start_at' => '2026-11-21 08:00:00',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '生产产线不在您的数据范围内');
});

it('rejects splitting a task for an order outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001');

    bearer($lineUser)->postJson("/api/v1/production/orders/{$orderB->id}/tasks", [
        'planned_quantity' => 10,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('rejects pausing a task outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001');
    $task = ProductionTask::factory()->create([
        'production_order_id' => $orderB->id,
        'production_line_id' => $lineB->id,
        'status' => ProductionTask::IN_PROGRESS,
    ]);

    bearer($lineUser)->postJson("/api/v1/production/tasks/{$task->id}/pause")
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('rejects assigning a task outside the scope', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001');
    $task = ProductionTask::factory()->create(['production_order_id' => $orderB->id]);

    bearer($lineUser)->putJson("/api/v1/production/tasks/{$task->id}", [
        'production_line_id' => $lineA1->id,
        'team_id' => Team::factory()->create()->id,
        'owner_id' => Employee::factory()->create()->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('rejects moving an order to a line outside the scope', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $order = createScopedOrder($lineA1, 'PO-A-001', ProductionOrder::DRAFT);

    bearer($lineUser)->putJson("/api/v1/production/orders/{$order->id}", [
        'production_line_id' => $lineB->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '生产产线不在您的数据范围内');
});

it('scopes the execution list through the line of their task or order', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $orderA = createScopedOrder($lineA1, 'PO-A-001');
    $orderB = createScopedOrder($lineB, 'PO-B-001');
    $taskA = ProductionTask::factory()->create(['production_order_id' => $orderA->id]);
    $taskB = ProductionTask::factory()->create(['production_order_id' => $orderB->id]);
    ProcessRecord::factory()->create(['production_task_id' => $taskA->id, 'sequence' => 1]);
    ProcessRecord::factory()->create(['production_task_id' => $taskB->id, 'sequence' => 1]);

    $taskIds = collect(bearer($lineUser)->getJson('/api/v1/production/process-records')
        ->assertOk()
        ->json('data.items'))->pluck('production_task_id')->unique()->values()->all();

    expect($taskIds)->toBe([$taskA->id]);
});

it('rejects every routed flow action on executions outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001');
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);
    $process = Process::factory()->create(['requires_inspection' => true]);
    $task = ProductionTask::factory()->create([
        'production_order_id' => $orderB->id,
        'status' => ProductionTask::IN_PROGRESS,
    ]);

    // One operation per action: (task, operation) is unique per execution.
    $record = fn (string $status, int $sequence): ProcessRecord => ProcessRecord::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => RouteOperation::factory()->create([
            'route_id' => $orderB->route_id,
            'process_id' => $process->id,
            'sequence' => $sequence,
        ])->id,
        'sequence' => $sequence,
        'status' => $status,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ]);

    bearer($lineUser)->postJson('/api/v1/production/process-records/'.$record(ProcessRecord::PENDING, 1)->id.'/start', [
        'employee_id' => $employee->id,
    ])->assertForbidden()->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson('/api/v1/production/process-records/'.$record(ProcessRecord::PROCESSING, 2)->id.'/complete')
        ->assertForbidden()->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson('/api/v1/production/process-records/'.$record(ProcessRecord::INSPECTED, 3)->id.'/submit')
        ->assertForbidden()->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson('/api/v1/production/process-records/'.$record(ProcessRecord::PENDING_TRANSFER, 4)->id.'/receive')
        ->assertForbidden()->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson('/api/v1/production/process-records/'.$record(ProcessRecord::PROCESSING, 5)->id.'/exception', [
        'reason' => '设备故障',
    ])->assertForbidden()->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson('/api/v1/production/process-records/'.$record(ProcessRecord::PROCESSING, 6)->id.'/rework', [
        'reason' => '尺寸超差',
    ])->assertForbidden()->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('rejects inspecting an execution outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001');
    $task = ProductionTask::factory()->create(['production_order_id' => $orderB->id]);
    $process = Process::factory()->create(['requires_inspection' => true]);
    $operation = RouteOperation::factory()->create([
        'route_id' => $orderB->route_id,
        'process_id' => $process->id,
        'sequence' => 1,
    ]);
    $record = ProcessRecord::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'sequence' => 1,
        'status' => ProcessRecord::INSPECTING,
    ]);

    // Inspect has no HTTP route of its own: quality inspection calls into the
    // service, so the guard is verified at that seam (ticket 08 covers the
    // end-to-end route). The request first establishes the user context the
    // guard resolves the scope from.
    bearer($lineUser)->getJson('/api/v1/me')->assertOk();

    expect(fn (): ProcessRecord => app(ProcessRecordService::class)->inspect($record, true, null))
        ->toThrow(ApiException::class, '该数据不在您的数据范围内');
});

it('hides executions of a task assigned to another line of the same factory', function () {
    // Assign puts a line on the task; that line then wins over the order's.
    [, $lineA1, $lineA2, , $lineUser] = productionScopeFixture();
    $orderA = createScopedOrder($lineA1, 'PO-A-001');
    $task = ProductionTask::factory()->create([
        'production_order_id' => $orderA->id,
        'production_line_id' => $lineA2->id,
    ]);
    ProcessRecord::factory()->create(['production_task_id' => $task->id, 'sequence' => 1]);

    bearer($lineUser)->getJson('/api/v1/production/process-records')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');
});

it('scopes the dispatch list and my dispatches by line', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $taskA = ProductionTask::factory()->create(['production_order_id' => createScopedOrder($lineA1, 'PO-A-001')->id]);
    $taskB = ProductionTask::factory()->create(['production_order_id' => createScopedOrder($lineB, 'PO-B-001')->id]);
    $employee = Employee::factory()->create();
    $operation = RouteOperation::factory()->create(['route_id' => $taskA->route_id, 'sequence' => 1]);
    $dispatchA = ProductionDispatch::factory()->create([
        'production_task_id' => $taskA->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
    ]);
    $dispatchB = ProductionDispatch::factory()->create([
        'production_task_id' => $taskB->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
    ]);
    $lineUser->update(['employee_id' => $employee->id]);

    $ids = collect(bearer($lineUser)->getJson('/api/v1/production/dispatches')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($ids)->toBe([$dispatchA->id]);

    $mine = collect(bearer($lineUser)->getJson('/api/v1/production/dispatches/mine')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($mine)->toBe([$dispatchA->id])
        ->and($mine)->not->toContain($dispatchB->id);
});

it('scopes the report list by line', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $taskA = ProductionTask::factory()->create(['production_order_id' => createScopedOrder($lineA1, 'PO-A-001')->id]);
    $taskB = ProductionTask::factory()->create(['production_order_id' => createScopedOrder($lineB, 'PO-B-001')->id]);
    $reportA = ProductionReport::factory()->create(['production_task_id' => $taskA->id]);
    $reportB = ProductionReport::factory()->create(['production_task_id' => $taskB->id]);

    $ids = collect(bearer($lineUser)->getJson('/api/v1/production/reports')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($ids)->toBe([$reportA->id])
        ->and($ids)->not->toContain($reportB->id);
});

it('rejects dispatching and reporting outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001');
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);
    $task = ProductionTask::factory()->create([
        'production_order_id' => $orderB->id,
        'status' => ProductionTask::IN_PROGRESS,
    ]);
    $operation = RouteOperation::factory()->create([
        'route_id' => $orderB->route_id,
        'sequence' => 1,
    ]);

    bearer($lineUser)->postJson('/api/v1/production/dispatches', [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson('/api/v1/production/reports', [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'quantity' => 5,
        'qualified_quantity' => 5,
        'defective_quantity' => 0,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});
