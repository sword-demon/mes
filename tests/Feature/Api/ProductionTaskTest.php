<?php

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;

/**
 * @param  array<string, mixed>  $overrides
 */
function releasedOrder(array $overrides = []): ProductionOrder
{
    return ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 100,
        ...$overrides,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function taskForOrder(ProductionOrder $order, array $overrides = []): ProductionTask
{
    return ProductionTask::factory()->create([
        'production_order_id' => $order->id,
        'product_id' => $order->product_id,
        'route_id' => $order->route_id,
        ...$overrides,
    ]);
}

/**
 * @return array{0: ProductionLine, 1: Team, 2: Employee}
 */
function taskAssignmentReferences(): array
{
    $line = ProductionLine::factory()->create();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);

    return [$line, $team, $employee];
}

it('splits a released order into tasks that inherit the product and route', function () {
    $order = releasedOrder();
    $actor = userWithPermissions(['production.tasks.create']);

    bearer($actor)->postJson("/api/v1/production/orders/{$order->id}/tasks", [
        'planned_quantity' => 40,
    ])->assertOk()
        ->assertJsonPath('data.code', "{$order->code}-T01")
        ->assertJsonPath('data.production_order.id', $order->id)
        ->assertJsonPath('data.product.id', $order->product_id)
        ->assertJsonPath('data.route.id', $order->route_id)
        ->assertJsonPath('data.planned_quantity', '40.000')
        ->assertJsonPath('data.produced_quantity', '0.000')
        ->assertJsonPath('data.progress_percent', 0)
        ->assertJsonPath('data.status', ProductionTask::PENDING)
        ->assertJsonPath('data.status_label', '待分配');

    bearer($actor)->postJson("/api/v1/production/orders/{$order->id}/tasks", [
        'planned_quantity' => 60,
    ])->assertOk()
        ->assertJsonPath('data.code', "{$order->code}-T02");
});

it('only splits orders that are released, running or paused', function () {
    $actor = userWithPermissions(['production.tasks.create']);

    foreach ([ProductionOrder::DRAFT, ProductionOrder::COMPLETED, ProductionOrder::CANCELLED] as $status) {
        $order = ProductionOrder::factory()->create(['status' => $status]);

        bearer($actor)->postJson("/api/v1/production/orders/{$order->id}/tasks", [
            'planned_quantity' => 10,
        ])->assertUnprocessable()
            ->assertJsonPath('message', '只有已下达的生产订单可以拆分生产任务');
    }

    $running = ProductionOrder::factory()->create(['status' => ProductionOrder::IN_PROGRESS]);

    bearer($actor)->postJson("/api/v1/production/orders/{$running->id}/tasks", [
        'planned_quantity' => 10,
    ])->assertOk();
});

it('refuses a split that would exceed the order planned quantity', function () {
    $order = releasedOrder(['planned_quantity' => 100]);
    $actor = userWithPermissions(['production.tasks.create']);
    taskForOrder($order, ['planned_quantity' => 60]);

    bearer($actor)->postJson("/api/v1/production/orders/{$order->id}/tasks", [
        'planned_quantity' => 40.001,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '生产任务计划数量合计不能超过订单计划数量');

    bearer($actor)->postJson("/api/v1/production/orders/{$order->id}/tasks", [
        'planned_quantity' => 40,
    ])->assertOk();
});

it('assigns an active line, team and owner and starts the task', function () {
    $order = releasedOrder();
    $task = taskForOrder($order);
    [$line, $team, $employee] = taskAssignmentReferences();
    $actor = userWithPermissions(['production.tasks.update']);

    bearer($actor)->putJson("/api/v1/production/tasks/{$task->id}", [
        'production_line_id' => $line->id,
        'team_id' => $team->id,
        'owner_id' => $employee->id,
    ])->assertOk()
        ->assertJsonPath('data.status', ProductionTask::IN_PROGRESS)
        ->assertJsonPath('data.status_label', '生产中')
        ->assertJsonPath('data.production_line.id', $line->id)
        ->assertJsonPath('data.team.id', $team->id)
        ->assertJsonPath('data.owner.id', $employee->id);
});

it('refuses to assign a disabled line, team or owner', function () {
    $order = releasedOrder();
    $task = taskForOrder($order);
    [$line, $team, $employee] = taskAssignmentReferences();
    $actor = userWithPermissions(['production.tasks.update']);

    $line->forceFill(['status' => false])->save();

    bearer($actor)->putJson("/api/v1/production/tasks/{$task->id}", [
        'production_line_id' => $line->id,
        'team_id' => $team->id,
        'owner_id' => $employee->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '产线不存在或已停用，不能分配给生产任务');

    $line->forceFill(['status' => true])->save();
    $team->forceFill(['status' => false])->save();

    bearer($actor)->putJson("/api/v1/production/tasks/{$task->id}", [
        'production_line_id' => $line->id,
        'team_id' => $team->id,
        'owner_id' => $employee->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '班组不存在或已停用，不能分配给生产任务');

    $team->forceFill(['status' => true])->save();
    $employee->forceFill(['status' => false])->save();

    bearer($actor)->putJson("/api/v1/production/tasks/{$task->id}", [
        'production_line_id' => $line->id,
        'team_id' => $team->id,
        'owner_id' => $employee->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '负责人不存在或已停用，不能分配给生产任务');
});

it('pauses, resumes and completes a task in the legal order', function () {
    $order = releasedOrder();
    [$line, $team, $employee] = taskAssignmentReferences();
    $task = taskForOrder($order, [
        'production_line_id' => $line->id,
        'team_id' => $team->id,
        'owner_id' => $employee->id,
        'status' => ProductionTask::IN_PROGRESS,
    ]);
    $actor = userWithPermissions([
        'production.tasks.pause',
        'production.tasks.resume',
        'production.tasks.complete',
    ]);

    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', ProductionTask::PAUSED);

    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/resume")
        ->assertOk()
        ->assertJsonPath('data.status', ProductionTask::IN_PROGRESS);

    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', ProductionTask::COMPLETED);

    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/pause")
        ->assertUnprocessable()
        ->assertJsonPath('message', '生产任务当前状态为「已完成」，不能变更为「已暂停」');
});

it('refuses to pause a task that was never started', function () {
    $task = taskForOrder(releasedOrder());
    $actor = userWithPermissions(['production.tasks.pause']);

    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/pause")
        ->assertUnprocessable()
        ->assertJsonPath('message', '生产任务当前状态为「待分配」，不能变更为「已暂停」');
});

it('derives task progress from reported quantities', function () {
    $order = releasedOrder();
    [$line, $team, $employee] = taskAssignmentReferences();
    $task = taskForOrder($order, [
        'production_line_id' => $line->id,
        'team_id' => $team->id,
        'owner_id' => $employee->id,
        'status' => ProductionTask::IN_PROGRESS,
        'planned_quantity' => 80,
    ]);
    $actor = userWithPermissions(['production.tasks.index']);

    $task->forceFill([
        'produced_quantity' => 20,
        'qualified_quantity' => 19,
        'defective_quantity' => 1,
    ])->save();

    bearer($actor)->getJson('/api/v1/production/tasks')
        ->assertOk()
        ->assertJsonPath('data.items.0.progress_percent', 25);

    $task->forceFill(['produced_quantity' => 60])->save();

    bearer($actor)->getJson('/api/v1/production/tasks')
        ->assertOk()
        ->assertJsonPath('data.items.0.progress_percent', 75);
});

it('filters tasks by order, status, line and keyword', function () {
    $order = releasedOrder();
    $other = releasedOrder();
    $line = ProductionLine::factory()->create();
    $actor = userWithPermissions(['production.tasks.index']);

    $matched = taskForOrder($order, [
        'production_line_id' => $line->id,
        'status' => ProductionTask::IN_PROGRESS,
    ]);
    taskForOrder($other, ['status' => ProductionTask::PENDING]);

    bearer($actor)->getJson('/api/v1/production/tasks?'.http_build_query([
        'production_order_id' => $order->id,
        'status' => ProductionTask::IN_PROGRESS,
        'production_line_id' => $line->id,
        'keyword' => $matched->code,
    ]))->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.code', $matched->code);
});

it('requires the matching permission for every task endpoint', function () {
    $actor = userWithPermissions([]);
    $task = taskForOrder(releasedOrder());

    bearer($actor)->getJson('/api/v1/production/tasks')
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    bearer($actor)->postJson("/api/v1/production/orders/{$task->production_order_id}/tasks", [])
        ->assertForbidden();

    bearer($actor)->putJson("/api/v1/production/tasks/{$task->id}", [])
        ->assertForbidden();

    bearer($actor)->postJson("/api/v1/production/tasks/{$task->id}/complete")
        ->assertForbidden();
});
