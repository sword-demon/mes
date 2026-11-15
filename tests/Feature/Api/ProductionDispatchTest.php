<?php

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;

/**
 * @return array{0: ProductionTask, 1: RouteOperation, 2: Team, 3: Employee}
 */
function dispatchReferences(): array
{
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 100,
    ]);
    $task = ProductionTask::factory()->create([
        'production_order_id' => $order->id,
        'product_id' => $order->product_id,
        'route_id' => $order->route_id,
        'planned_quantity' => 40,
    ]);
    $operation = RouteOperation::factory()->create(['route_id' => $task->route_id]);
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);

    return [$task, $operation, $team, $employee];
}

it('creates a dispatch for a task operation and employee', function () {
    [$task, $operation, $team, $employee] = dispatchReferences();
    $actor = userWithPermissions(['production.dispatches.create']);

    bearer($actor)->postJson('/api/v1/production/dispatches', [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
        'dispatched_at' => '2026-11-15 08:30:00',
    ])->assertOk()
        ->assertJsonPath('data.code', "{$task->code}-D01")
        ->assertJsonPath('data.production_task.id', $task->id)
        ->assertJsonPath('data.production_order.id', $task->production_order_id)
        ->assertJsonPath('data.route_operation.id', $operation->id)
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.team.id', $team->id);

    $this->assertDatabaseHas('mes_dispatch_orders', [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ]);
});

it('refuses to dispatch the same task operation to the same employee twice', function () {
    [$task, $operation, $team, $employee] = dispatchReferences();
    $actor = userWithPermissions(['production.dispatches.create']);
    $payload = [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ];

    bearer($actor)->postJson('/api/v1/production/dispatches', $payload)->assertOk();

    bearer($actor)->postJson('/api/v1/production/dispatches', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', '该任务的工序已派给该人员，不能重复派工');

    expect(ProductionDispatch::query()->count())->toBe(1);
});

it('rejects a dispatch whose operation is not on the task route', function () {
    [$task, , $team, $employee] = dispatchReferences();
    $otherOperation = RouteOperation::factory()->create();
    $actor = userWithPermissions(['production.dispatches.create']);

    bearer($actor)->postJson('/api/v1/production/dispatches', [
        'production_task_id' => $task->id,
        'route_operation_id' => $otherOperation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '工序不属于该生产任务的工艺路线');
});

it('rejects disabled workers, disabled teams and workers from another team', function () {
    [$task, $operation, $team, $employee] = dispatchReferences();
    $actor = userWithPermissions(['production.dispatches.create']);
    $payload = [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ];

    $team->forceFill(['status' => false])->save();

    bearer($actor)->postJson('/api/v1/production/dispatches', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', '班组不存在或已停用，不能用于派工');

    $team->forceFill(['status' => true])->save();
    $employee->forceFill(['status' => false])->save();

    bearer($actor)->postJson('/api/v1/production/dispatches', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', '作业人员不存在或已停用，不能用于派工');

    $employee->forceFill(['status' => true])->save();
    $outsider = Employee::factory()->create(['team_id' => Team::factory()->create()->id]);

    bearer($actor)->postJson('/api/v1/production/dispatches', [
        ...$payload,
        'employee_id' => $outsider->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '作业人员不属于所选班组');
});

it('refuses to dispatch a completed task', function () {
    [$task, $operation, $team, $employee] = dispatchReferences();
    $task->forceFill(['status' => ProductionTask::COMPLETED])->save();
    $actor = userWithPermissions(['production.dispatches.create']);

    bearer($actor)->postJson('/api/v1/production/dispatches', [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '生产任务已完成，不能派工');
});

it('filters dispatch records by task, order, employee and date', function () {
    [$task, $operation, $team, $employee] = dispatchReferences();
    $otherTask = ProductionTask::factory()->create();
    $otherOperation = RouteOperation::factory()->create(['route_id' => $otherTask->route_id]);
    $otherEmployee = Employee::factory()->create(['team_id' => $team->id]);
    $actor = userWithPermissions(['production.dispatches.index']);

    $matched = ProductionDispatch::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
        'dispatched_at' => '2026-11-12 09:00:00',
    ]);
    ProductionDispatch::factory()->create([
        'production_task_id' => $otherTask->id,
        'route_operation_id' => $otherOperation->id,
        'employee_id' => $otherEmployee->id,
        'team_id' => $team->id,
        'dispatched_at' => '2026-11-10 09:00:00',
    ]);

    bearer($actor)->getJson('/api/v1/production/dispatches?'.http_build_query([
        'production_task_id' => $task->id,
        'production_order_id' => $task->production_order_id,
        'employee_id' => $employee->id,
        'start_date' => '2026-11-11',
        'end_date' => '2026-11-13',
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $matched->id);
});

it('ignores array-shaped dispatch filters instead of failing', function () {
    $actor = userWithPermissions(['production.dispatches.index']);

    bearer($actor)->getJson('/api/v1/production/dispatches?'.http_build_query([
        'start_date' => ['2026-11-01'],
        'end_date' => ['2026-11-30'],
        'production_order_id' => ['1'],
        'production_task_id' => ['1'],
        'employee_id' => ['1'],
        'keyword' => ['PD-1'],
        'per_page' => ['100'],
    ]))->assertOk();
});

it('returns only the current user own dispatches from mine', function () {
    [$task, $operation, $team, $employee] = dispatchReferences();
    $otherEmployee = Employee::factory()->create(['team_id' => $team->id]);
    $actor = userWithPermissions(['production.dispatches.mine'], 'dispatch_worker');
    $actor->update(['employee_id' => $employee->id]);

    $own = ProductionDispatch::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ]);
    ProductionDispatch::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $otherEmployee->id,
        'team_id' => $team->id,
    ]);
    $completedTask = ProductionTask::factory()->create([
        'route_id' => $task->route_id,
        'status' => ProductionTask::COMPLETED,
    ]);
    ProductionDispatch::factory()->create([
        'production_task_id' => $completedTask->id,
        'route_operation_id' => RouteOperation::factory()->create([
            'route_id' => $completedTask->route_id,
            'sequence' => 2,
        ])->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ]);

    bearer($actor)->getJson('/api/v1/production/dispatches/mine')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $own->id);
});

it('returns an empty mine page when the account has no employee record', function () {
    $actor = userWithPermissions(['production.dispatches.mine']);

    bearer($actor)->getJson('/api/v1/production/dispatches/mine')
        ->assertOk()
        ->assertJsonCount(0, 'data.items')
        ->assertJsonPath('data.meta.total', 0);
});

it('validates the required dispatch fields', function () {
    $actor = userWithPermissions(['production.dispatches.create']);
    $response = bearer($actor)->postJson('/api/v1/production/dispatches', [])
        ->assertUnprocessable();

    expect(array_keys($response->json('data.errors')))
        ->toContain('production_task_id')
        ->toContain('route_operation_id')
        ->toContain('employee_id')
        ->toContain('team_id');
});

it('requires authentication for dispatch endpoints', function () {
    $this->getJson('/api/v1/production/dispatches')
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);

    $this->getJson('/api/v1/production/dispatches/mine')
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
});

it('lists the operations of a task route for dispatch', function () {
    [$task, $operation] = dispatchReferences();
    $actor = userWithPermissions(['production.dispatches.create']);

    bearer($actor)->getJson("/api/v1/production/tasks/{$task->id}/operations")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $operation->id)
        ->assertJsonPath('data.0.process.code', $operation->process->code);
});

it('requires the matching permission for every dispatch endpoint', function () {
    [$task, $operation, $team, $employee] = dispatchReferences();
    $actor = userWithPermissions([]);

    bearer($actor)->getJson('/api/v1/production/dispatches')->assertForbidden();
    bearer($actor)->getJson('/api/v1/production/dispatches/mine')->assertForbidden();
    bearer($actor)->getJson("/api/v1/production/tasks/{$task->id}/operations")->assertForbidden();
    bearer($actor)->postJson('/api/v1/production/dispatches', [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $team->id,
    ])->assertForbidden();
});
