<?php

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Material\Model\MaterialReturn;
use App\Modules\Material\Model\MaterialReturnItem;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Model\Workshop;
use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionException;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Production\Service\ProcessRecordService;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\System\Model\Department;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

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
        'production.sns.index',
        'production.sns.create',
        'production.sns.void',
        'production.exceptions.index',
        'production.exceptions.create',
        'production.exceptions.process',
        'quality.inspections.index',
        'quality.inspections.create',
        'quality.inspections.record',
        'quality.defects.index',
        'quality.defects.create',
        'quality.defects.process',
        'quality.defects.scrap',
        'inventory.index',
        'inventory.inbound',
        'inventory.outbound',
        'inventory.adjust',
        'inventory.options',
        'dashboard.overview',
        'dashboard.trend',
        'dashboard.quality',
        'dashboard.equipment',
        'equipment.ledger.index',
        'equipment.ledger.create',
        'equipment.ledger.update',
        'equipment.monitor.index',
        'equipment.history.index',
        'equipment.alarms.index',
        'equipment.alarms.confirm',
        'material.requisitions.index',
        'material.requisitions.create',
        'material.requisitions.issue',
        'material.returns.index',
        'material.returns.create',
        'material.returns.audit',
    ], 'scope_director_a');
    $lineUser->update(['factory_id' => $factoryA->id]);
    $lineUser->productionLines()->sync([$lineA1->id]);

    $factoryUser = userWithPermissions([
        'data.scope.factory',
        'production.orders.index',
        'production.tasks.index',
        'inventory.index',
        'inventory.inbound',
        'inventory.outbound',
        'inventory.adjust',
        'dashboard.overview',
        'dashboard.trend',
        'dashboard.quality',
        'dashboard.equipment',
    ], 'scope_factory_admin');
    $factoryUser->update(['factory_id' => $factoryA->id]);

    return [$factoryA, $lineA1, $lineA2, $lineB, $lineUser, $factoryUser];
}

/**
 * A material batch issued to two orders on different lines, each with one
 * serial number, plus outbound transactions and requisitions.
 *
 * @return array{factoryA: Factory, orderA: ProductionOrder, orderB: ProductionOrder, snA: ProductSn, snB: ProductSn}
 */
function backwardTraceScopeFixture(): array
{
    [$factoryA, $lineA1, , $lineB, , $factoryUser] = productionScopeFixture();
    $material = Material::factory()->create(['batch_managed' => true]);
    $warehouseA = Warehouse::factory()->create(['factory_id' => $factoryA->id]);
    $locationA = StorageLocation::factory()->create(['warehouse_id' => $warehouseA->id]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $locationA->id,
        'batch_no' => 'LOT-SCOPE-001',
    ]);
    $orderA = createScopedOrder($lineA1, 'PO-A-TRACE', ProductionOrder::IN_PROGRESS);
    $orderB = createScopedOrder($lineB, 'PO-B-TRACE', ProductionOrder::IN_PROGRESS);

    foreach ([$orderA, $orderB] as $order) {
        $requisition = MaterialRequisition::query()->create([
            'code' => 'MR-'.str_replace('PO-', '', $order->code),
            'production_order_id' => $order->id,
            'status' => MaterialRequisition::ISSUED,
            'created_by_id' => $factoryUser->id,
        ]);
        InventoryTransaction::query()->create([
            'inventory_id' => $inventory->id,
            'material_id' => $material->id,
            'business_type' => InventoryTransaction::OUTBOUND,
            'quantity' => -5,
            'before_quantity' => 100,
            'after_quantity' => 95,
            'reference_no' => $requisition->code,
            'created_by_id' => $factoryUser->id,
        ]);
    }

    $batchA = ProductionBatch::factory()->create(['production_order_id' => $orderA->id, 'code' => 'PB-A-TRACE']);
    $batchB = ProductionBatch::factory()->create(['production_order_id' => $orderB->id, 'code' => 'PB-B-TRACE']);
    $snA = ProductSn::factory()->create(['sn' => 'SN-A-TRACE', 'production_order_id' => $orderA->id, 'production_batch_id' => $batchA->id]);
    $snB = ProductSn::factory()->create(['sn' => 'SN-B-TRACE', 'production_order_id' => $orderB->id, 'production_batch_id' => $batchB->id]);

    return compact('factoryA', 'orderA', 'orderB', 'snA', 'snB');
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

it('limits the user list to the actor factory', function () {
    [$factoryA] = factoryWithLines();
    $factoryB = Factory::factory()->create();

    $actor = userWithPermissions(['data.scope.factory', 'system.users.index'], 'factory_admin');
    $actor->update(['factory_id' => $factoryA->id]);
    $peer = User::factory()->create(['factory_id' => $factoryA->id]);
    $outsider = User::factory()->create(['factory_id' => $factoryB->id]);

    $ids = collect(bearer($actor)->getJson('/api/v1/system/users')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->json('data.items'))->pluck('id');

    expect($ids)->toContain($actor->id, $peer->id)->not->toContain($outsider->id);
});

it('filters the user list by factory for an all-scope actor', function () {
    [$factoryA] = factoryWithLines();
    $factoryB = Factory::factory()->create();

    $actor = userWithPermissions(['system.users.index']);
    User::factory()->create(['factory_id' => $factoryA->id]);
    $target = User::factory()->create(['factory_id' => $factoryB->id]);

    bearer($actor)->getJson("/api/v1/system/users?factory_id={$factoryB->id}")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.id', $target->id);
});

it('rejects modifying a user from another factory', function () {
    [$factoryA] = factoryWithLines();
    $factoryB = Factory::factory()->create();

    $actor = userWithPermissions(['data.scope.factory', 'system.users.update', 'system.users.reset-password'], 'factory_admin');
    $actor->update(['factory_id' => $factoryA->id]);
    $target = User::factory()->create(['factory_id' => $factoryB->id, 'name' => '外厂用户']);

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}", ['name' => '被篡改'])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/password", ['password' => 'replacement-password'])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    expect($target->fresh()->name)->toBe('外厂用户');
});

it('rejects moving a user or binding lines outside the actor factory', function () {
    [$factoryA] = factoryWithLines();
    $factoryB = Factory::factory()->create();
    $workshopB = Workshop::factory()->create(['factory_id' => $factoryB->id]);
    $lineB = ProductionLine::factory()->create(['workshop_id' => $workshopB->id]);

    $actor = userWithPermissions(['data.scope.factory', 'system.users.update'], 'factory_admin');
    $actor->update(['factory_id' => $factoryA->id]);
    $target = User::factory()->create(['factory_id' => $factoryA->id]);

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}", ['factory_id' => $factoryB->id])
        ->assertForbidden();

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}", ['factory_id' => null])
        ->assertForbidden();

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}", ['production_line_ids' => [$lineB->id]])
        ->assertForbidden();

    expect($target->fresh()->factory_id)->toBe($factoryA->id)
        ->and($target->productionLines()->count())->toBe(0);
});

it('creates users in the actor factory and rejects another factory', function () {
    [$factoryA] = factoryWithLines();
    $factoryB = Factory::factory()->create();

    $actor = userWithPermissions(['data.scope.factory', 'system.users.create'], 'factory_admin');
    $actor->update(['factory_id' => $factoryA->id]);

    bearer($actor)->postJson('/api/v1/system/users', [
        'name' => '本厂新用户',
        'email' => 'own-factory@mes.test',
        'password' => 'password-123',
    ])->assertOk()
        ->assertJsonPath('data.factory_id', $factoryA->id);

    bearer($actor)->postJson('/api/v1/system/users', [
        'name' => '外厂新用户',
        'email' => 'other-factory@mes.test',
        'password' => 'password-123',
        'factory_id' => $factoryB->id,
    ])->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    expect(User::query()->where('email', 'other-factory@mes.test')->exists())->toBeFalse();
});

it('rejects assigning roles to a user from another factory', function () {
    [$factoryA] = factoryWithLines();
    $factoryB = Factory::factory()->create();

    $actor = userWithPermissions(['data.scope.factory', 'system.users.assign-roles'], 'factory_admin');
    $actor->update(['factory_id' => $factoryA->id]);
    $target = User::factory()->create(['factory_id' => $factoryB->id]);
    $role = Role::factory()->create();

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/roles", ['role_ids' => [$role->id]])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    expect($target->roles()->count())->toBe(0);
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

it('scopes the serial number list and hides out-of-scope details', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $snA = ProductSn::factory()->create(['production_order_id' => createScopedOrder($lineA1, 'PO-A-001')->id]);
    $snB = ProductSn::factory()->create(['production_order_id' => createScopedOrder($lineB, 'PO-B-001')->id]);

    bearer($lineUser)->getJson('/api/v1/production/sns')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.sn', $snA->sn);

    // A missing SN and an out-of-scope SN are indistinguishable: both 404.
    bearer($lineUser)->getJson("/api/v1/production/sns/{$snB->sn}")
        ->assertNotFound()
        ->assertJsonPath('message', 'SN 不存在');
});

it('scopes the exception list and keeps unlinked rows to the all scope', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $exceptionA = ProductionException::factory()->create([
        'production_order_id' => createScopedOrder($lineA1, 'PO-A-001')->id,
    ]);
    ProductionException::factory()->create([
        'production_order_id' => createScopedOrder($lineB, 'PO-B-001')->id,
    ]);
    $unlinked = ProductionException::factory()->create([
        'production_order_id' => null,
        'process_record_id' => null,
    ]);

    $codes = collect(bearer($lineUser)->getJson('/api/v1/production/exceptions')
        ->assertOk()
        ->json('data.items'))->pluck('code')->values()->all();

    expect($codes)->toBe([$exceptionA->code]);

    // Legacy rows with no order and no execution are only for the all scope.
    $allCodes = collect(bearer(superAdmin())->getJson('/api/v1/production/exceptions')
        ->assertOk()
        ->json('data.items'))->pluck('code')->values()->all();

    expect($allCodes)->toHaveCount(3)
        ->and($allCodes)->toContain($exceptionA->code)
        ->and($allCodes)->toContain($unlinked->code);
});

it('rejects SN actions on orders outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001', ProductionOrder::RELEASED);
    $sn = ProductSn::factory()->create(['production_order_id' => $orderB->id]);

    bearer($lineUser)->postJson('/api/v1/production/sns', [
        'production_order_id' => $orderB->id,
        'quantity' => 1,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson("/api/v1/production/sns/{$sn->sn}/void", ['reason' => '错发'])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('rejects handling exceptions outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $exception = ProductionException::factory()->create([
        'production_order_id' => createScopedOrder($lineB, 'PO-B-001')->id,
        'status' => ProductionException::REPORTED,
    ]);

    bearer($lineUser)->postJson("/api/v1/production/exceptions/{$exception->id}/process", [
        'handling_result' => '已维修',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('rejects an exception without an order or an execution', function () {
    // Built by hand: the helper would append data.scope.all, and this case is
    // about the D1 link requirement, not scoping.
    $role = Role::factory()->create(['code' => 'exception_reporter']);
    $role->permissions()->attach(Permission::query()->firstOrCreate(
        ['code' => 'production.exceptions.create'],
        ['name' => 'production.exceptions.create', 'type' => 'api'],
    ));
    $user = User::factory()->create();
    $user->roles()->attach($role);

    bearer($user)->postJson('/api/v1/production/exceptions', [
        'type' => 'equipment',
        'description' => '设备异常',
    ])->assertUnprocessable(function (TestResponse $response): void {
        expect($response->json('message'))->toContain('请关联生产订单或工序执行记录');
    });
});

it('scopes the inspection task list by the order line', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $inspectionA = InspectionTask::factory()->create([
        'production_order_id' => createScopedOrder($lineA1, 'PO-A-001')->id,
    ]);
    InspectionTask::factory()->create([
        'production_order_id' => createScopedOrder($lineB, 'PO-B-001')->id,
    ]);

    $ids = collect(bearer($lineUser)->getJson('/api/v1/quality/inspection-tasks')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($ids)->toBe([$inspectionA->id]);
});

it('rejects creating an inspection task outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001', ProductionOrder::IN_PROGRESS);
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
        'completed_at' => now()->subMinute(),
    ]);

    bearer($lineUser)->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $orderB->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('rejects recording results and images outside the scope', function () {
    // End-to-end regression for the flow-inspect guard wired in ticket 05:
    // quality inspection is the HTTP entry that reaches it.
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001', ProductionOrder::IN_PROGRESS);
    $inspection = InspectionTask::factory()->create(['production_order_id' => $orderB->id]);
    $item = InspectionItem::factory()->create();

    bearer($lineUser)->postJson("/api/v1/quality/inspection-tasks/{$inspection->id}/record", [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => '5']],
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson("/api/v1/quality/inspection-tasks/{$inspection->id}/images", [
        'images' => [UploadedFile::fake()->image('shot.jpg')],
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('scopes the defective product list by the order line', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $defectA = DefectiveProduct::factory()->create([
        'production_order_id' => createScopedOrder($lineA1, 'PO-A-001')->id,
    ]);
    DefectiveProduct::factory()->create([
        'production_order_id' => createScopedOrder($lineB, 'PO-B-001')->id,
    ]);

    $ids = collect(bearer($lineUser)->getJson('/api/v1/quality/defective-products')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($ids)->toBe([$defectA->id]);
});

it('rejects registering and handling defects outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $orderB = createScopedOrder($lineB, 'PO-B-001', ProductionOrder::IN_PROGRESS);
    $process = Process::factory()->create();
    $operation = RouteOperation::factory()->create([
        'route_id' => $orderB->route_id,
        'process_id' => $process->id,
        'sequence' => 1,
    ]);
    $batch = ProductionBatch::factory()->create(['production_order_id' => $orderB->id, 'quantity' => 10]);
    $code = DefectCode::factory()->create(['status' => true]);
    $defect = DefectiveProduct::factory()->create([
        'production_order_id' => $orderB->id,
        'route_operation_id' => $operation->id,
        'production_batch_id' => $batch->id,
        'defect_code_id' => $code->id,
        'status' => DefectiveProduct::PROCESSING,
    ]);

    bearer($lineUser)->postJson('/api/v1/quality/defective-products', [
        'production_order_id' => $orderB->id,
        'route_operation_id' => $operation->id,
        'production_batch_id' => $batch->id,
        'defect_code_id' => $code->id,
        'quantity' => 5,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson("/api/v1/quality/defective-products/{$defect->id}/start")
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson("/api/v1/quality/defective-products/{$defect->id}/scrap", [
        'reason' => '无法返修',
        'quantity' => 5,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

/**
 * Two factories, one warehouse per factory with a location, plus the
 * line- and factory-scoped users of the first factory.
 *
 * @return array{0: Warehouse, 1: StorageLocation, 2: Warehouse, 3: StorageLocation, 4: User, 5: User}
 */
function inventoryScopeFixture(): array
{
    [, , , , $lineUser, $factoryUser] = productionScopeFixture();
    $factoryA = Factory::query()->orderBy('id')->firstOrFail();
    $warehouseA = Warehouse::factory()->create(['factory_id' => $factoryA->id]);
    $locationA = StorageLocation::factory()->create(['warehouse_id' => $warehouseA->id]);

    $factoryB = Factory::factory()->create();
    $warehouseB = Warehouse::factory()->create(['factory_id' => $factoryB->id]);
    $locationB = StorageLocation::factory()->create(['warehouse_id' => $warehouseB->id]);

    return [$warehouseA, $locationA, $warehouseB, $locationB, $lineUser, $factoryUser];
}

it('scopes the inventory list, movements and options by warehouse factory', function () {
    [$warehouseA, $locationA, , , $lineUser, $factoryUser] = inventoryScopeFixture();
    $material = Material::factory()->create(['batch_managed' => false]);
    $inventoryA = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $locationA->id,
        'current_quantity' => 5,
    ]);
    $factoryUnassigned = Factory::factory()->create();
    $warehouseUnassigned = Warehouse::factory()->create();
    $locationUnassigned = StorageLocation::factory()->create(['warehouse_id' => $warehouseUnassigned->id]);
    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $locationUnassigned->id,
        'current_quantity' => 7,
    ]);

    $ids = collect(bearer($factoryUser)->getJson('/api/v1/inventory')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($ids)->toBe([$inventoryA->id]);

    $warehouseCodes = collect(bearer($lineUser)->getJson('/api/v1/inventory/options')
        ->assertOk()
        ->json('data.warehouses'))->pluck('code')->values()->all();

    expect($warehouseCodes)->toBe([$warehouseA->code]);
});

it('rejects inventory movements across factories', function () {
    [, , $warehouseB, $locationB, $lineUser, $factoryUser] = inventoryScopeFixture();
    $material = Material::factory()->create(['batch_managed' => false]);

    bearer($factoryUser)->postJson('/api/v1/inventory/inbound', [
        'material_id' => $material->id,
        'warehouse_id' => $warehouseB->id,
        'location_id' => $locationB->id,
        'quantity' => 1,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($factoryUser)->postJson('/api/v1/inventory/outbound', [
        'material_id' => $material->id,
        'warehouse_id' => $warehouseB->id,
        'location_id' => $locationB->id,
        'quantity' => 1,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($factoryUser)->postJson('/api/v1/inventory/adjust', [
        'material_id' => $material->id,
        'warehouse_id' => $warehouseB->id,
        'location_id' => $locationB->id,
        'current_quantity' => 3,
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('scopes the requisition and return lists by the order line', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $orderA = createScopedOrder($lineA1, 'PO-A-001', ProductionOrder::RELEASED);
    $orderB = createScopedOrder($lineB, 'PO-B-001', ProductionOrder::RELEASED);
    $requisitionA = MaterialRequisition::query()->create(['code' => 'MR-A-001', 'production_order_id' => $orderA->id, 'status' => MaterialRequisition::PENDING, 'created_by_id' => 1]);
    $requisitionB = MaterialRequisition::query()->create(['code' => 'MR-B-001', 'production_order_id' => $orderB->id, 'status' => MaterialRequisition::PENDING, 'created_by_id' => 1]);
    $returnA = MaterialReturn::query()->create(['code' => 'RT-A-001', 'requisition_id' => $requisitionA->id, 'production_order_id' => $orderA->id, 'status' => MaterialReturn::PENDING, 'created_by_id' => 1]);
    $returnB = MaterialReturn::query()->create(['code' => 'RT-B-001', 'requisition_id' => $requisitionB->id, 'production_order_id' => $orderB->id, 'status' => MaterialReturn::PENDING, 'created_by_id' => 1]);

    $requisitionIds = collect(bearer($lineUser)->getJson('/api/v1/materials/requisitions')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($requisitionIds)->toBe([$requisitionA->id]);

    $returnIds = collect(bearer($lineUser)->getJson('/api/v1/materials/returns')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($returnIds)->toBe([$returnA->id]);
});

it('rejects issuing and approving outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $factoryB = $lineB->workshop->factory_id;
    $orderB = createScopedOrder($lineB, 'PO-B-001', ProductionOrder::RELEASED);
    $material = Material::factory()->create(['batch_managed' => false]);
    $requisition = MaterialRequisition::query()->create(['code' => 'MR-B-002', 'production_order_id' => $orderB->id, 'status' => MaterialRequisition::PENDING, 'created_by_id' => 1]);
    $requisitionItem = MaterialRequisitionItem::query()->create([
        'requisition_id' => $requisition->id,
        'material_id' => $material->id,
        'requested_quantity' => 10,
        'issued_quantity' => 10,
    ]);
    $return = MaterialReturn::query()->create(['code' => 'RT-B-002', 'requisition_id' => $requisition->id, 'production_order_id' => $orderB->id, 'status' => MaterialReturn::PENDING, 'created_by_id' => 1]);
    $returnItem = MaterialReturnItem::query()->create([
        'material_return_id' => $return->id,
        'requisition_item_id' => $requisitionItem->id,
        'quantity' => 2,
    ]);
    $warehouseB = Warehouse::factory()->create(['factory_id' => $factoryB]);
    $locationB = StorageLocation::factory()->create(['warehouse_id' => $warehouseB->id]);

    bearer($lineUser)->postJson("/api/v1/materials/requisitions/{$requisition->id}/issue", [
        'items' => [['id' => $requisitionItem->id, 'warehouse_id' => $warehouseB->id, 'location_id' => $locationB->id, 'quantity' => 1]],
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson("/api/v1/materials/returns/{$return->id}/approve", [
        'items' => [['id' => $returnItem->id, 'warehouse_id' => $warehouseB->id, 'location_id' => $locationB->id]],
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('scopes the equipment list, monitor and alarms by line', function () {
    [, $lineA1, , $lineB, $lineUser] = productionScopeFixture();
    $equipmentA = Equipment::factory()->create(['production_line_id' => $lineA1->id]);
    $equipmentB = Equipment::factory()->create(['production_line_id' => $lineB->id]);
    EquipmentAlarm::factory()->create(['equipment_id' => $equipmentA->id]);
    EquipmentAlarm::factory()->create(['equipment_id' => $equipmentB->id]);

    $ids = collect(bearer($lineUser)->getJson('/api/v1/equipments')
        ->assertOk()
        ->json('data.items'))->pluck('id')->values()->all();

    expect($ids)->toBe([$equipmentA->id]);

    $alarmIds = collect(bearer($lineUser)->getJson('/api/v1/equipments/alarms')
        ->assertOk()
        ->json('data.items'))->pluck('equipment_id')->unique()->values()->all();

    expect($alarmIds)->toBe([$equipmentA->id]);
});

it('rejects ledger edits and alarm handling outside the scope', function () {
    [, , , $lineB, $lineUser] = productionScopeFixture();
    $equipmentB = Equipment::factory()->create(['production_line_id' => $lineB->id]);
    $alarm = EquipmentAlarm::factory()->create(['equipment_id' => $equipmentB->id]);

    bearer($lineUser)->putJson("/api/v1/equipments/{$equipmentB->id}", [
        'name' => '改名设备',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->postJson("/api/v1/equipments/alarms/{$alarm->id}/confirm")
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');

    bearer($lineUser)->getJson("/api/v1/equipments/{$equipmentB->id}/history?start_at=2026-11-21 08:00:00&end_at=2026-11-21 18:00:00")
        ->assertForbidden()
        ->assertJsonPath('message', '该数据不在您的数据范围内');
});

it('fails closed when the guard receives a model without a scope declaration', function () {
    // A model reaching the guard without declaring how it resolves to a line
    // or factory must error out loudly instead of being silently allowed.
    // Unreachable through business APIs (every scoped service's models
    // implement an interface), so the guard's public contract is tested
    // directly.
    // Establish the request context first: outside a request the guard
    // resolves to `all` and the fail-closed path never triggers.
    bearer(userWithPermissions(['data.scope.line'], 'unscoped_probe'))->getJson('/api/v1/me')->assertOk();

    $guard = app(DataScopeGuard::class);
    $department = Department::factory()->create();

    expect(fn () => $guard->ensure($department))
        ->toThrow(ApiException::class, '该数据不在您的数据范围内');
});

it('narrows every dashboard statistic to the scoped lines', function () {
    [, $lineA1, $lineA2, $lineB, $lineUser, $factoryUser] = productionScopeFixture();

    $orderA = ProductionOrder::factory()->create([
        'production_line_id' => $lineA1->id,
        'planned_quantity' => 10,
        'status' => ProductionOrder::RELEASED,
    ]);
    $reportA = ProductionReport::factory()->create([
        'production_task_id' => ProductionTask::factory()->create([
            'production_order_id' => $orderA->id,
            'production_line_id' => $lineA1->id,
        ])->id,
        'quantity' => 6,
        'qualified_quantity' => 5,
        'defective_quantity' => 1,
        'reported_at' => now()->startOfDay()->utc()->addHours(12),
    ]);
    Equipment::factory()->create(['production_line_id' => $lineA1->id, 'status' => Equipment::RUNNING]);

    $orderB = ProductionOrder::factory()->create([
        'production_line_id' => $lineB->id,
        'planned_quantity' => 10,
        'status' => ProductionOrder::RELEASED,
    ]);
    ProductionReport::factory()->create([
        'production_task_id' => ProductionTask::factory()->create([
            'production_order_id' => $orderB->id,
            'production_line_id' => $lineB->id,
        ])->id,
        'quantity' => 99,
        'qualified_quantity' => 99,
        'defective_quantity' => 0,
        'reported_at' => now()->startOfDay()->utc()->addHours(12),
    ]);
    Equipment::factory()->create(['production_line_id' => $lineB->id, 'status' => Equipment::RUNNING]);

    $today = bearer($lineUser)->getJson('/api/v1/dashboard/today')->assertOk()->json('data');

    expect($today['order_count'])->toBe(1)
        ->and($today['produced_quantity'])->toBe('6.000')
        ->and($today['running_equipment_count'])->toBe(1);

    bearer($lineUser)->getJson('/api/v1/dashboard/production-trend')
        ->assertOk()
        ->assertJsonPath('data.items.6.quantity', '6.000');

    bearer($lineUser)->getJson('/api/v1/dashboard/latest-orders')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $orderA->id);

    bearer($lineUser)->getJson('/api/v1/dashboard/latest-reports')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $reportA->id);

    bearer($lineUser)->getJson('/api/v1/dashboard/latest-exceptions')
        ->assertOk()
        ->assertJsonCount(0, 'data.items');

    $statuses = collect(bearer($lineUser)->getJson('/api/v1/dashboard/equipment-status')
        ->assertOk()
        ->json('data.items'));

    expect($statuses->where('status', 'running')->sole()['count'])->toBe(1)
        ->and($statuses->sum('count'))->toBe(1);

    // 工厂范围用户看本工厂两条产线（A1 与 A2 空产量），不含工厂 B。
    $response = bearer($factoryUser)->getJson('/api/v1/dashboard/production-lines');
    $lineIds = collect($response->assertOk()
        ->json('data.items'))->pluck('production_line_id')->values()->sort()->values()->all();

    // Lines are ordered by code in the response; scope is what must hold.
    expect($lineIds)->toBe(collect([$lineA1->id, $lineA2->id])->sort()->values()->all())
        ->and($lineIds)->not->toContain($lineB->id);
});

it('returns 404 when a forward trace hits a serial number outside the scope', function () {
    [, $lineA1, , $lineB] = productionScopeFixture();
    $snA = ProductSn::factory()->create(['production_order_id' => createScopedOrder($lineA1, 'PO-A-SN', ProductionOrder::IN_PROGRESS)->id]);
    $snB = ProductSn::factory()->create(['production_order_id' => createScopedOrder($lineB, 'PO-B-SN', ProductionOrder::IN_PROGRESS)->id]);
    $lineUser = userWithPermissions(['data.scope.line', 'traceability.forward.show'], 'scope_forward_line');
    $lineUser->update(['factory_id' => Factory::query()->orderBy('id')->firstOrFail()->id]);
    $lineUser->productionLines()->sync([$lineA1->id]);

    bearer($lineUser)->getJson("/api/v1/traceability/sn/{$snA->sn}")
        ->assertOk()
        ->assertJsonPath('data.sn.sn', $snA->sn);

    // Out-of-scope SN reads as missing, not forbidden.
    bearer($lineUser)->getJson("/api/v1/traceability/sn/{$snB->sn}")
        ->assertNotFound()
        ->assertJsonPath('code', 404)
        ->assertJsonPath('message', 'SN 不存在');

    bearer(superAdmin())->getJson("/api/v1/traceability/sn/{$snB->sn}")
        ->assertOk()
        ->assertJsonPath('data.sn.sn', $snB->sn);
});

it('filters backward trace results by the order line', function () {
    ['factoryA' => $factoryA, 'orderA' => $orderA, 'orderB' => $orderB, 'snA' => $snA, 'snB' => $snB] = backwardTraceScopeFixture();
    $lineUser = userWithPermissions(['data.scope.line', 'traceability.backward.show'], 'scope_trace_line');
    $lineUser->update(['factory_id' => $factoryA->id]);
    $lineUser->productionLines()->sync([$orderA->production_line_id]);

    // The out-of-scope order, batch and SN are absent; only the in-scope row stays.
    bearer($lineUser)->getJson('/api/v1/traceability/material/LOT-SCOPE-001')
        ->assertOk()
        ->assertJsonPath('data.summary.production_order_count', 1)
        ->assertJsonPath('data.summary.production_batch_count', 1)
        ->assertJsonPath('data.summary.sn_count', 1)
        ->assertJsonPath('data.production_batches.0.production_order.code', $orderA->code)
        ->assertJsonPath('data.production_batches.0.product_sns.0.sn', $snA->sn)
        ->assertJsonMissing(['code' => $orderB->code])
        ->assertJsonMissing(['sn' => $snB->sn]);
});

it('returns 404 when the batch movements sit in another factory', function () {
    [$factoryA, $lineA1, , , , $factoryUser] = productionScopeFixture();
    $actor = userWithPermissions(['data.scope.factory', 'traceability.backward.show'], 'scope_trace_factory');
    $actor->update(['factory_id' => $factoryA->id]);

    $material = Material::factory()->create(['batch_managed' => true]);
    $order = createScopedOrder($lineA1, 'PO-A-TRACE-OUT', ProductionOrder::IN_PROGRESS);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-A-TRACE-OUT',
        'production_order_id' => $order->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $factoryUser->id,
    ]);
    $warehouseB = Warehouse::factory()->create(['factory_id' => Factory::factory()->create()->id]);
    $locationB = StorageLocation::factory()->create(['warehouse_id' => $warehouseB->id]);
    $inventoryB = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $locationB->id,
        'batch_no' => 'LOT-SCOPE-FACTORY',
    ]);
    $transaction = InventoryTransaction::query()->create([
        'inventory_id' => $inventoryB->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -5,
        'before_quantity' => 100,
        'after_quantity' => 95,
        'reference_no' => $requisition->code,
        'created_by_id' => $factoryUser->id,
    ]);

    // The order and requisition are in range, only the warehouse is not.
    bearer($actor)->getJson('/api/v1/traceability/material/LOT-SCOPE-FACTORY')
        ->assertNotFound()
        ->assertJsonPath('message', '物料批次不存在');

    $warehouseA = Warehouse::factory()->create(['factory_id' => $factoryA->id]);
    $locationA = StorageLocation::factory()->create(['warehouse_id' => $warehouseA->id]);
    $inventoryA = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $locationA->id,
        'batch_no' => 'LOT-SCOPE-FACTORY',
    ]);
    $transaction->update(['inventory_id' => $inventoryA->id]);
    $batch = ProductionBatch::factory()->create(['production_order_id' => $order->id]);
    ProductSn::factory()->create(['production_order_id' => $order->id, 'production_batch_id' => $batch->id]);

    bearer($actor)->getJson('/api/v1/traceability/material/LOT-SCOPE-FACTORY')
        ->assertOk()
        ->assertJsonPath('data.summary.sn_count', 1);
});

it('hides serial numbers whose order is outside the scope', function () {
    [$factoryA, $lineA1, , $lineB] = productionScopeFixture();
    $lineUser = userWithPermissions(['data.scope.line', 'traceability.backward.show'], 'scope_trace_sn');
    $lineUser->update(['factory_id' => $factoryA->id]);
    $lineUser->productionLines()->sync([$lineA1->id]);

    $material = Material::factory()->create(['batch_managed' => true]);
    $order = createScopedOrder($lineA1, 'PO-A-TRACE-SN', ProductionOrder::IN_PROGRESS);
    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-A-TRACE-SN',
        'production_order_id' => $order->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $lineUser->id,
    ]);
    $warehouse = Warehouse::factory()->create(['factory_id' => $factoryA->id]);
    $location = StorageLocation::factory()->create(['warehouse_id' => $warehouse->id]);
    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => 'LOT-SCOPE-SN',
    ]);
    InventoryTransaction::query()->create([
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -5,
        'before_quantity' => 100,
        'after_quantity' => 95,
        'reference_no' => $requisition->code,
        'created_by_id' => $lineUser->id,
    ]);
    $batch = ProductionBatch::factory()->create(['production_order_id' => $order->id]);
    $outOfScopeOrder = createScopedOrder($lineB, 'PO-B-TRACE-SN', ProductionOrder::IN_PROGRESS);
    $sn = ProductSn::factory()->create([
        'production_order_id' => $outOfScopeOrder->id,
        'production_batch_id' => $batch->id,
    ]);

    // The batch belongs to an in-scope order; the SN row does not.
    bearer($lineUser)->getJson('/api/v1/traceability/material/LOT-SCOPE-SN')
        ->assertNotFound();

    bearer(superAdmin())->getJson('/api/v1/traceability/material/LOT-SCOPE-SN')
        ->assertOk()
        ->assertJsonPath('data.summary.sn_count', 1)
        ->assertJsonPath('data.production_batches.0.product_sns.0.sn', $sn->sn);
});
