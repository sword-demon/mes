<?php

use App\Modules\Material\Model\Material;
use App\Modules\Organization\Model\Employee;
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
        $log = OperationLog::query()->create([
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
        ]);
        $log->forceFill(['created_at' => $createdAt])->save();

        return $log;
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
