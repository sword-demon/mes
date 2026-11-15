<?php

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;

/**
 * @return array{0: ProductionTask, 1: RouteOperation, 2: Employee}
 */
function reportReferences(array $taskOverrides = [], array $orderOverrides = []): array
{
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 100,
        ...$orderOverrides,
    ]);
    $task = ProductionTask::factory()->create([
        'production_order_id' => $order->id,
        'product_id' => $order->product_id,
        'route_id' => $order->route_id,
        'planned_quantity' => 40,
        'status' => ProductionTask::IN_PROGRESS,
        ...$taskOverrides,
    ]);
    $operation = RouteOperation::factory()->create(['route_id' => $task->route_id]);
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);

    return [$task, $operation, $employee];
}

/**
 * @return array<string, mixed>
 */
function reportPayload(ProductionTask $task, RouteOperation $operation, Employee $employee): array
{
    return [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'quantity' => 12,
        'qualified_quantity' => 10,
        'defective_quantity' => 2,
        'reported_at' => '2026-11-15 09:30:00',
    ];
}

it('reports production and rolls the quantities up to the task and order', function () {
    [$task, $operation, $employee] = reportReferences();
    $actor = userWithPermissions(['production.reports.create']);

    bearer($actor)->postJson('/api/v1/production/reports', reportPayload($task, $operation, $employee))
        ->assertOk()
        ->assertJsonPath('data.code', "{$task->code}-R01")
        ->assertJsonPath('data.type', ProductionReport::NORMAL)
        ->assertJsonPath('data.quantity', '12.000')
        ->assertJsonPath('data.production_task.id', $task->id)
        ->assertJsonPath('data.production_order.id', $task->production_order_id)
        ->assertJsonPath('data.route_operation.id', $operation->id)
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.team.id', $employee->team_id);

    $this->assertDatabaseHas('mes_production_reports', [
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $employee->team_id,
        'quantity' => 12,
        'qualified_quantity' => 10,
        'defective_quantity' => 2,
        'type' => ProductionReport::NORMAL,
    ]);
    $this->assertDatabaseHas('mes_production_tasks', [
        'id' => $task->id,
        'produced_quantity' => 12,
        'qualified_quantity' => 10,
        'defective_quantity' => 2,
    ]);
    $this->assertDatabaseHas('mes_production_orders', [
        'id' => $task->production_order_id,
        'produced_quantity' => 12,
        'qualified_quantity' => 10,
        'defective_quantity' => 2,
    ]);
});

it('accepts three-decimal quantities when qualified plus defective equals the total', function () {
    [$task, $operation, $employee] = reportReferences(taskOverrides: ['planned_quantity' => 1]);
    $actor = userWithPermissions(['production.reports.create']);

    bearer($actor)->postJson('/api/v1/production/reports', [
        ...reportPayload($task, $operation, $employee),
        'quantity' => 0.3,
        'qualified_quantity' => 0.1,
        'defective_quantity' => 0.2,
        'type' => ProductionReport::BATCH,
    ])->assertOk()
        ->assertJsonPath('data.type', ProductionReport::BATCH)
        ->assertJsonPath('data.quantity', '0.300');

    expect($task->fresh()->produced_quantity)->toBe('0.300');
});

it('only reports on tasks of released, unfinished orders', function () {
    $actor = userWithPermissions(['production.reports.create']);

    foreach ([
        ProductionOrder::DRAFT,
        ProductionOrder::APPROVED,
        ProductionOrder::COMPLETED,
        ProductionOrder::CANCELLED,
    ] as $status) {
        [$task, $operation, $employee] = reportReferences(orderOverrides: ['status' => $status]);

        bearer($actor)->postJson('/api/v1/production/reports', reportPayload($task, $operation, $employee))
            ->assertUnprocessable()
            ->assertJsonPath('message', '只有已下达且未完成的生产任务可以报工');
    }

    [$task, $operation, $employee] = reportReferences(taskOverrides: ['status' => ProductionTask::COMPLETED]);

    bearer($actor)->postJson('/api/v1/production/reports', reportPayload($task, $operation, $employee))
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有已下达且未完成的生产任务可以报工');
});

it('refuses a report larger than the task remainder', function () {
    [$task, $operation, $employee] = reportReferences(taskOverrides: ['produced_quantity' => 39]);
    $actor = userWithPermissions(['production.reports.create']);

    bearer($actor)->postJson('/api/v1/production/reports', [
        ...reportPayload($task, $operation, $employee),
        'quantity' => 1.5,
        'qualified_quantity' => 1.5,
        'defective_quantity' => 0,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '报工数量不能超过任务剩余数量');

    expect(ProductionReport::query()->count())->toBe(0);
});

it('refuses a report whose qualified and defective quantities do not add up', function () {
    [$task, $operation, $employee] = reportReferences();
    $actor = userWithPermissions(['production.reports.create']);

    bearer($actor)->postJson('/api/v1/production/reports', [
        ...reportPayload($task, $operation, $employee),
        'qualified_quantity' => 9,
        'defective_quantity' => 2,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '合格数 + 不良数必须等于报工数');

    expect(ProductionReport::query()->count())->toBe(0);
});

it('refuses an operation from another route and a disabled operator', function () {
    [$task, $operation, $employee] = reportReferences();
    $actor = userWithPermissions(['production.reports.create']);
    $otherOperation = RouteOperation::factory()->create();

    bearer($actor)->postJson('/api/v1/production/reports', [
        ...reportPayload($task, $operation, $employee),
        'route_operation_id' => $otherOperation->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '工序不属于该生产任务的工艺路线');

    $employee->forceFill(['status' => false])->save();

    bearer($actor)->postJson('/api/v1/production/reports', reportPayload($task, $operation, $employee))
        ->assertUnprocessable()
        ->assertJsonPath('message', '作业人员不存在或已停用，不能报工');
});

it('lists reports filtered by task, order and type', function () {
    [$task, $operation, $employee] = reportReferences();
    $actor = userWithPermissions(['production.reports.index']);
    $other = ProductionReport::factory()->create();
    $report = ProductionReport::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'employee_id' => $employee->id,
        'team_id' => $employee->team_id,
        'type' => ProductionReport::REVERSED,
    ]);

    bearer($actor)->getJson("/api/v1/production/reports?production_task_id={$task->id}")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.id', $report->id)
        ->assertJsonPath('data.items.0.production_order.id', $task->production_order_id)
        ->assertJsonPath('data.items.0.type_label', '已冲销');

    bearer($actor)->getJson("/api/v1/production/reports?production_order_id={$task->production_order_id}&type=reversed")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);

    bearer($actor)->getJson('/api/v1/production/reports?type=normal')
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.id', $other->id);
});

it('reverses a report and rolls the quantities back without deleting it', function () {
    [$task, $operation, $employee] = reportReferences();
    $actor = userWithPermissions(['production.reports.create', 'production.reports.reverse']);

    bearer($actor)->postJson('/api/v1/production/reports', reportPayload($task, $operation, $employee))
        ->assertOk();
    $report = ProductionReport::query()->firstOrFail();

    bearer($actor)->postJson("/api/v1/production/reports/{$report->id}/reverse")
        ->assertOk()
        ->assertJsonPath('data.type', ProductionReport::REVERSED)
        ->assertJsonPath('data.type_label', '已冲销');

    $this->assertDatabaseHas('mes_production_reports', [
        'id' => $report->id,
        'type' => ProductionReport::REVERSED,
    ]);
    $this->assertDatabaseHas('mes_production_tasks', [
        'id' => $task->id,
        'produced_quantity' => 0,
        'qualified_quantity' => 0,
        'defective_quantity' => 0,
    ]);
    $this->assertDatabaseHas('mes_production_orders', [
        'id' => $task->production_order_id,
        'produced_quantity' => 0,
        'qualified_quantity' => 0,
        'defective_quantity' => 0,
    ]);

    bearer($actor)->postJson("/api/v1/production/reports/{$report->id}/reverse")
        ->assertUnprocessable()
        ->assertJsonPath('message', '报工已冲销，不能重复冲销');

    bearer($actor)->deleteJson("/api/v1/production/reports/{$report->id}")
        ->assertNotFound();
});

it('requires permission to report production', function () {
    [$task, $operation, $employee] = reportReferences();
    $actor = userWithPermissions(['production.reports.index']);

    bearer($actor)->postJson('/api/v1/production/reports', reportPayload($task, $operation, $employee))
        ->assertForbidden();
});

it('offers report options to an operator who can only report', function () {
    [$task, $operation, $employee] = reportReferences();
    [$completedTask] = reportReferences(taskOverrides: ['status' => ProductionTask::COMPLETED]);
    $actor = userWithPermissions(['production.reports.create']);

    $response = bearer($actor)->getJson('/api/v1/production/reports/options')
        ->assertOk()
        ->assertJsonFragment(['id' => $task->id, 'remaining_quantity' => '40.000'])
        ->assertJsonFragment(['id' => $employee->id]);

    expect(collect($response->json('data.tasks'))->pluck('id'))
        ->toContain($task->id)
        ->not->toContain($completedTask->id);

    bearer($actor)->getJson("/api/v1/production/reports/tasks/{$task->id}/operations")
        ->assertOk()
        ->assertJsonPath('data.0.id', $operation->id);
});

it('does not expose report options without report creation permission', function () {
    $actor = userWithPermissions(['production.reports.index']);

    bearer($actor)->getJson('/api/v1/production/reports/options')->assertForbidden();
});

it('refuses to reverse when the task counters no longer cover the report', function () {
    [$task, $operation, $employee] = reportReferences();
    $actor = userWithPermissions(['production.reports.create', 'production.reports.reverse']);

    bearer($actor)->postJson('/api/v1/production/reports', reportPayload($task, $operation, $employee))
        ->assertOk();
    $report = ProductionReport::query()->firstOrFail();
    $task->refresh();
    $task->forceFill(['produced_quantity' => 0])->save();

    bearer($actor)->postJson("/api/v1/production/reports/{$report->id}/reverse")
        ->assertUnprocessable()
        ->assertJsonPath('message', '冲销数量超过任务或订单累计数量，数据不一致');

    expect($report->fresh()->type)->toBe(ProductionReport::NORMAL);
});

it('requires authentication for report endpoints', function () {
    $this->getJson('/api/v1/production/reports')->assertUnauthorized();
});
