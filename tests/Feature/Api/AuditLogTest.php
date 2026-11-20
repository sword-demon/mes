<?php

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Material\Model\Material;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\System\Model\LoginLog;
use App\Modules\System\Model\OperationLog;
use App\Modules\System\Model\Permission;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use App\Modules\System\Service\LoginLogService;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use RuntimeException;

it('records successful and failed login attempts with account, ip and result', function () {
    $user = User::factory()->create([
        'email' => 'audit@mes.test',
        'password' => 'secret-password',
    ]);

    $this->postJson('/api/v1/auth/tokens', [
        'email' => 'audit@mes.test',
        'password' => 'secret-password',
        'device_name' => 'web',
    ])->assertOk();

    $this->postJson('/api/v1/auth/tokens', [
        'email' => 'audit@mes.test',
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    $this->postJson('/api/v1/auth/tokens', [
        'email' => 'missing@mes.test',
        'password' => 'whatever',
    ])->assertUnprocessable();

    expect(LoginLog::query()->count())->toBe(3);
    $this->assertDatabaseHas('sys_login_logs', [
        'account' => 'audit@mes.test',
        'user_id' => $user->id,
        'success' => true,
        'ip' => '127.0.0.1',
        'message' => '登录成功',
    ]);
    $this->assertDatabaseHas('sys_login_logs', [
        'account' => 'audit@mes.test',
        'user_id' => $user->id,
        'success' => false,
        'message' => '密码错误',
    ]);
    $this->assertDatabaseHas('sys_login_logs', [
        'account' => 'missing@mes.test',
        'user_id' => null,
        'success' => false,
        'message' => '账号不存在',
    ]);
});

it('records a disabled account rejection in the login log', function () {
    User::factory()->create([
        'email' => 'disabled@mes.test',
        'password' => 'secret-password',
        'status' => false,
    ]);

    $this->postJson('/api/v1/auth/tokens', [
        'email' => 'disabled@mes.test',
        'password' => 'secret-password',
    ])->assertUnprocessable();

    $this->assertDatabaseHas('sys_login_logs', [
        'account' => 'disabled@mes.test',
        'success' => false,
        'message' => '账号已停用',
    ]);
});

it('records an operation log for a production order transition', function () {
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::DRAFT]);
    $actor = userWithPermissions(['production.orders.submit']);

    bearer($actor)->postJson("/api/v1/production/orders/{$order->id}/submit")->assertOk();

    $log = OperationLog::query()->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->user_name)->toBe($actor->name)
        ->and($log->action)->toBe('production.order.transition')
        ->and($log->action_name)->toBe('订单状态流转')
        ->and($log->object_type)->toBe('生产订单')
        ->and($log->object_id)->toBe($order->id)
        ->and($log->object_name)->toBe($order->code)
        ->and($log->ip)->toBe('127.0.0.1')
        ->and($log->changes)->toBe(['状态' => ['before' => '草稿', 'after' => '待审核']]);
});

it('records operation logs for report submission and reversal', function () {
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 100,
    ]);
    $task = ProductionTask::factory()->create([
        'production_order_id' => $order->id,
        'product_id' => $order->product_id,
        'route_id' => $order->route_id,
        'planned_quantity' => 40,
        'status' => ProductionTask::IN_PROGRESS,
    ]);
    $operation = RouteOperation::factory()->create(['route_id' => $task->route_id]);
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);
    $actor = userWithPermissions(['production.reports.create', 'production.reports.reverse']);

    $reportId = bearer($actor)->postJson('/api/v1/production/reports', [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'quantity' => 12,
        'qualified_quantity' => 10,
        'defective_quantity' => 2,
        'reported_at' => '2026-11-15 09:30:00',
    ])->assertOk()->json('data.id');

    bearer($actor)->postJson("/api/v1/production/reports/{$reportId}/reverse")->assertOk();

    $logs = OperationLog::query()->orderBy('id')->get();
    $report = ProductionReport::query()->findOrFail($reportId);

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->action)->toBe('production.report.create')
        ->and($logs[0]->object_name)->toBe($report->code)
        ->and($logs[0]->changes['任务累计产量'])->toBe(['before' => '0.000', 'after' => '12.000'])
        ->and($logs[1]->action)->toBe('production.report.reverse')
        ->and($logs[1]->changes['报工状态'])->toBe(['before' => '正常', 'after' => '已冲销'])
        ->and($logs[1]->changes['任务累计产量'])->toBe(['before' => '12.000', 'after' => '0.000']);
});

it('records an operation log for an inventory movement', function () {
    [$warehouse, $location] = inventoryLocation();
    $material = Material::factory()->create([
        'code' => 'M001',
        'name' => '冷轧钢板',
        'batch_managed' => false,
    ]);
    $actor = userWithPermissions(['inventory.inbound']);

    bearer($actor)->postJson('/api/v1/inventory/inbound', [
        'material_id' => $material->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => $location->id,
        'quantity' => 10,
    ])->assertOk();

    $log = OperationLog::query()->sole();

    expect($log->action)->toBe('inventory.inbound')
        ->and($log->action_name)->toBe('库存入库')
        ->and($log->object_type)->toBe('库存')
        ->and($log->object_name)->toContain('M001')
        ->and($log->changes['当前库存'])->toBe(['before' => '0.000', 'after' => '10.000']);
});

it('records an operation log when a role permission set changes', function () {
    $before = Permission::factory()->create(['code' => 'system.users.index', 'type' => 'api']);
    $after = Permission::factory()->create(['code' => 'system.users.create', 'type' => 'api']);
    $role = Role::factory()->create(['name' => '质检员']);
    $role->permissions()->attach($before);
    $actor = superAdmin();

    bearer($actor)->putJson("/api/v1/system/roles/{$role->id}", [
        'permission_ids' => [$after->id],
    ])->assertOk();

    $log = OperationLog::query()->sole();

    expect($log->action)->toBe('system.role.updated')
        ->and($log->object_type)->toBe('角色')
        ->and($log->object_name)->toBe('质检员')
        ->and($log->changes['权限'])->toBe([
            'before' => ['system.users.index'],
            'after' => ['system.users.create'],
        ]);
});

it('records an operation log when user roles are assigned', function () {
    $target = User::factory()->create(['name' => '张三']);
    $role = Role::factory()->create(['name' => '质检员']);
    $actor = userWithPermissions(['system.users.assign-roles']);

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/roles", [
        'role_ids' => [$role->id],
    ])->assertOk();

    $log = OperationLog::query()->sole();

    expect($log->action)->toBe('system.user.roles-assigned')
        ->and($log->object_name)->toBe('张三')
        ->and($log->changes['角色'])->toBe([
            'before' => [],
            'after' => ['质检员'],
        ]);
});

it('truncates an oversized login account to the audit column limit', function () {
    app(LoginLogService::class)->record(str_repeat('a', 300), null, false, '账号不存在');

    expect(mb_strlen(LoginLog::query()->sole()->account))->toBe(255);
});

it('truncates an oversized operation object name to the audit column limit', function () {
    $actor = User::factory()->create();

    app(OperationLogService::class)->record(new OperationLogEntry(
        action: 'smoke.test',
        actionName: '冒烟测试',
        objectType: '库存',
        objectId: 1,
        objectName: str_repeat('长', 300),
    ), $actor);

    expect(mb_strlen(OperationLog::query()->sole()->object_name))->toBe(255);
});

it('rolls back a role assignment when the audit log fails', function () {
    $target = User::factory()->create();
    $role = Role::factory()->create();
    $actor = userWithPermissions(['system.users.assign-roles']);

    $this->mock(OperationLogService::class, function ($mock): void {
        $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
    });

    $this->withoutExceptionHandling();

    expect(fn () => bearer($actor)->putJson("/api/v1/system/users/{$target->id}/roles", [
        'role_ids' => [$role->id],
    ]))->toThrow(RuntimeException::class);

    expect($target->roles()->count())->toBe(0);
});

it('records operation logs for user creation, update and password reset', function () {
    $role = Role::factory()->create();
    $actor = userWithPermissions([
        'system.users.create',
        'system.users.update',
        'system.users.reset-password',
        'system.users.assign-roles',
    ]);

    $userId = bearer($actor)->postJson('/api/v1/system/users', [
        'name' => '王五',
        'email' => 'wangwu@mes.test',
        'password' => 'Password123!',
        'role_ids' => [$role->id],
    ])->assertOk()->json('data.id');

    bearer($actor)->putJson("/api/v1/system/users/{$userId}", [
        'name' => '王五改',
    ])->assertOk();

    bearer($actor)->putJson("/api/v1/system/users/{$userId}/password", [
        'password' => 'NewPassword123!',
    ])->assertOk();

    expect(OperationLog::query()->orderBy('id')->pluck('action')->all())->toBe([
        'system.user.created',
        'system.user.updated',
        'system.user.password-reset',
    ]);
});

it('records operation logs for outbound and adjustment inventory movements', function () {
    [$warehouse, $location] = inventoryLocation();
    $material = Material::factory()->create(['batch_managed' => false]);
    Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'current_quantity' => 20,
        'frozen_quantity' => 0,
    ]);
    $actor = userWithPermissions(['inventory.outbound', 'inventory.adjust']);

    bearer($actor)->postJson('/api/v1/inventory/outbound', [
        'material_id' => $material->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => $location->id,
        'quantity' => 5,
    ])->assertOk();

    bearer($actor)->postJson('/api/v1/inventory/adjust', [
        'material_id' => $material->id,
        'warehouse_id' => $warehouse->id,
        'location_id' => $location->id,
        'current_quantity' => 12,
    ])->assertOk();

    $logs = OperationLog::query()->orderBy('id')->get();

    expect($logs[0]->action)->toBe('inventory.outbound')
        ->and($logs[0]->action_name)->toBe('库存出库')
        ->and($logs[0]->changes['当前库存'])->toBe(['before' => '20.000', 'after' => '15.000'])
        ->and($logs[1]->action)->toBe('inventory.adjustment')
        ->and($logs[1]->action_name)->toBe('库存调整')
        ->and($logs[1]->changes['当前库存'])->toBe(['before' => '15.000', 'after' => '12.000']);
});

it('lists login logs and filters by account, result and date', function () {
    $viewer = userWithPermissions(['system.logs.index']);
    $success = LoginLog::factory()->create([
        'account' => 'alice@mes.test',
        'success' => true,
        'message' => '登录成功',
    ]);
    $success->forceFill(['created_at' => '2026-11-15 10:00:00'])->save();
    $failure = LoginLog::factory()->create([
        'account' => 'bob@mes.test',
        'success' => false,
        'message' => '密码错误',
    ]);
    $failure->forceFill(['created_at' => '2026-11-16 10:00:00'])->save();

    bearer($viewer)->getJson('/api/v1/system/login-logs')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.items.0.account', 'bob@mes.test');

    bearer($viewer)->getJson('/api/v1/system/login-logs?'.http_build_query([
        'account' => 'alice',
    ]))->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.success', true);

    bearer($viewer)->getJson('/api/v1/system/login-logs?'.http_build_query([
        'success' => '0',
    ]))->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.account', 'bob@mes.test');

    bearer($viewer)->getJson('/api/v1/system/login-logs?'.http_build_query([
        'start_date' => '2026-11-16',
        'end_date' => '2026-11-16',
    ]))->assertOk()->assertJsonPath('data.meta.total', 1);

    bearer($viewer)->getJson('/api/v1/system/login-logs')
        ->assertOk()
        ->assertJsonPath('data.items.0.message', '密码错误');
});

it('denies the login log list without the log permission', function () {
    bearer(userWithPermissions([], 'login_denied'))
        ->getJson('/api/v1/system/login-logs')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('denies the operation log list without the log permission', function () {
    bearer(userWithPermissions([], 'audit_denied'))
        ->getJson('/api/v1/system/operation-logs')
        ->assertForbidden()
        ->assertJsonPath('code', 403);
});

it('lists operation logs and filters by user, action and date', function () {
    $viewer = userWithPermissions(['system.logs.index']);
    $other = User::factory()->create(['name' => '李四']);
    $viewer->update(['name' => '张三']);

    $make = function (User $user, string $action, string $actionName, string $createdAt): OperationLog {
        return OperationLog::factory()->create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $action,
            'action_name' => $actionName,
            'object_type' => '生产订单',
            'object_id' => 1,
            'object_name' => 'PO-001',
            'summary' => $actionName.'：生产订单 PO-001',
            'changes' => ['状态' => ['before' => '草稿', 'after' => '待审核']],
            'ip' => '127.0.0.1',
            'created_at' => $createdAt,
        ]);
    };

    $make($viewer, 'production.order.transition', '订单状态流转', '2026-11-14 10:00:00');
    $make($other, 'inventory.inbound', '库存入库', '2026-11-15 10:00:00');
    $make($viewer, 'production.order.transition', '订单状态流转', '2026-11-16 10:00:00');

    bearer($viewer)->getJson('/api/v1/system/operation-logs')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 3)
        ->assertJsonPath('data.items.0.user_name', '张三')
        ->assertJsonPath('data.items.0.changes.状态.before', '草稿');

    bearer($viewer)->getJson('/api/v1/system/operation-logs?'.http_build_query([
        'user_id' => $other->id,
    ]))->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.action', 'inventory.inbound');

    bearer($viewer)->getJson('/api/v1/system/operation-logs?'.http_build_query([
        'action' => 'production.order.transition',
    ]))->assertOk()->assertJsonPath('data.meta.total', 2);

    bearer($viewer)->getJson('/api/v1/system/operation-logs?'.http_build_query([
        'start_date' => '2026-11-15',
        'end_date' => '2026-11-15',
    ]))->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.user_name', '李四');

    $options = bearer($viewer)->getJson('/api/v1/system/operation-logs/options')
        ->assertOk()
        ->assertJsonCount(2, 'data.users')
        ->assertJsonCount(2, 'data.actions');

    expect(collect($options->json('data.users'))->pluck('name')->all())
        ->toContain('张三')
        ->toContain('李四');
});

it('records operation logs for order creation and edit', function () {
    $order = createScopedOrder(ProductionLine::factory()->create(), 'PO-AUDIT-001', ProductionOrder::DRAFT);
    $actor = superAdmin();

    expect(OperationLog::query()->count())->toBe(0);

    bearer($actor)->postJson('/api/v1/production/orders', [
        'code' => 'PO-AUDIT-002',
        'product_id' => $order->product_id,
        'bom_id' => $order->bom_id,
        'route_id' => $order->route_id,
        'production_line_id' => $order->production_line_id,
        'planned_quantity' => 50,
        'planned_start_at' => '2026-11-21 08:00:00',
    ])->assertOk();

    bearer($actor)->putJson("/api/v1/production/orders/{$order->id}", [
        'remark' => '改期首单',
    ])->assertOk();

    $logs = OperationLog::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->action)->toBe('production.order.create')
        ->and($logs[0]->action_name)->toBe('新增生产订单')
        ->and($logs[0]->object_name)->toBe('PO-AUDIT-002')
        ->and($logs[1]->action)->toBe('production.order.update')
        ->and($logs[1]->changes['备注'])->toBe(['before' => null, 'after' => '改期首单']);
});

it('records split, assign and transition logs for tasks', function () {
    [$factory, $line] = factoryWithLines();
    $order = createScopedOrder($line, 'PO-AUDIT-003');
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);
    $actor = superAdmin();

    bearer($actor)->postJson("/api/v1/production/orders/{$order->id}/tasks", [
        'planned_quantity' => 40,
    ])->assertOk();

    $task = ProductionTask::query()->where('production_order_id', $order->id)->sole();

    bearer($actor)->putJson("/api/v1/production/tasks/{$task->id}", [
        'production_line_id' => $line->id,
        'team_id' => $team->id,
        'owner_id' => $employee->id,
    ])->assertOk();

    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/pause")->assertOk();
    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/resume")->assertOk();
    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/complete")->assertOk();

    $actions = OperationLog::query()->orderBy('id')->pluck('action')->all();

    expect($actions)->toBe([
        'production.task.split',
        'production.task.assign',
        'production.task.pause',
        'production.task.resume',
        'production.task.complete',
    ]);

    $assign = OperationLog::query()->where('action', 'production.task.assign')->sole();

    expect($assign->object_name)->toBe($task->code)
        ->and($assign->changes['产线'])->toBe(['before' => null, 'after' => $line->name])
        ->and($assign->changes['状态'])->toBe(['before' => '待分配', 'after' => '生产中']);
});
