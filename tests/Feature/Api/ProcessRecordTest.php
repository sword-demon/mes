<?php

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Team;
use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Service\ProductionTaskService;

/**
 * Splits a released order into one task whose route carries one operation per
 * entry in `$requiresInspection`, then returns the task, its executions in
 * route order and an active operator.
 *
 * @param  array<int, bool>  $requiresInspection
 * @param  array<int, int>  $sequences  explicit route sequences, defaulting to 1..n
 * @return array{0: ProductionTask, 1: Collection<int, ProcessRecord>, 2: Employee}
 */
function flowTask(array $requiresInspection = [false, true], array $sequences = []): array
{
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::RELEASED,
        'planned_quantity' => 10,
    ]);

    foreach ($requiresInspection as $index => $requires) {
        RouteOperation::factory()->create([
            'route_id' => $order->route_id,
            'process_id' => Process::factory()->create(['requires_inspection' => $requires])->id,
            'sequence' => $sequences[$index] ?? $index + 1,
        ]);
    }

    $task = app(ProductionTaskService::class)->split($order, ['planned_quantity' => 10]);
    $records = ProcessRecord::query()
        ->where('production_task_id', $task->id)
        ->orderBy('sequence')
        ->get();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);

    return [$task, $records, $employee];
}

it('creates a pending execution for every route operation when a task is split', function () {
    [$task, $records] = flowTask([false, true, false]);

    expect($records)->toHaveCount(3)
        ->and($records->pluck('sequence')->all())->toBe([1, 2, 3])
        ->and($records->pluck('status')->all())->toBe(array_fill(0, 3, ProcessRecord::PENDING))
        ->and($records->pluck('production_task_id')->unique()->all())->toBe([$task->id]);
});

it('starts an operation and records its operator, equipment and SN', function () {
    [$task, $records, $employee] = flowTask([false]);
    $actor = userWithPermissions(['production.flows.start']);
    $record = $records->first();

    bearer($actor)->postJson("/api/v1/production/process-records/{$record->id}/start", [
        'employee_id' => $employee->id,
        'equipment_id' => 42,
        'sn' => 'MES20261115000001',
    ])->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::PROCESSING)
        ->assertJsonPath('data.status_label', '加工中')
        ->assertJsonPath('data.sn', 'MES20261115000001')
        ->assertJsonPath('data.equipment_id', 42)
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.team.id', $employee->team_id)
        ->assertJsonPath('data.production_task.id', $task->id)
        ->assertJsonPath('data.production_order.id', $task->production_order_id)
        ->assertJsonPath('data.route_operation.sequence', 1)
        ->assertJsonPath('data.route_operation.process.requires_inspection', false);

    expect($record->fresh()->started_at)->not->toBeNull();
});

it('refuses to start a later operation before the previous one is transferred', function () {
    [, $records, $employee] = flowTask([false, false]);
    $actor = userWithPermissions([
        'production.flows.start',
        'production.flows.complete',
        'production.flows.receive',
    ]);
    [$first, $second] = [$records[0], $records[1]];

    bearer($actor)->postJson("/api/v1/production/process-records/{$second->id}/start", [
        'employee_id' => $employee->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '上道工序尚未流转，不能开始本道工序');

    bearer($actor)->postJson("/api/v1/production/process-records/{$first->id}/start", [
        'employee_id' => $employee->id,
    ])->assertOk();
    bearer($actor)->postJson("/api/v1/production/process-records/{$first->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::PENDING_TRANSFER);

    expect($first->fresh()->submitted_at)->not->toBeNull();

    bearer($actor)->postJson("/api/v1/production/process-records/{$first->id}/receive")
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::TRANSFERRED);

    bearer($actor)->postJson("/api/v1/production/process-records/{$second->id}/start", [
        'employee_id' => $employee->id,
    ])->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::PROCESSING);
});

it('treats the lowest route sequence as the first operation', function () {
    [, $records, $employee] = flowTask([false, false], [10, 20]);
    $actor = userWithPermissions(['production.flows.start']);

    bearer($actor)->postJson("/api/v1/production/process-records/{$records[1]->id}/start", [
        'employee_id' => $employee->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '上道工序尚未流转，不能开始本道工序');

    bearer($actor)->postJson("/api/v1/production/process-records/{$records[0]->id}/start", [
        'employee_id' => $employee->id,
    ])->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::PROCESSING);
});

it('gates submission of an inspection process behind a passed inspection', function () {
    [, $records, $employee] = flowTask([true]);
    $actor = userWithPermissions([
        'production.flows.start',
        'production.flows.complete',
        'production.flows.inspect',
        'production.flows.submit',
        'production.flows.receive',
    ]);
    $record = $records->first();
    $url = "/api/v1/production/process-records/{$record->id}";

    bearer($actor)->postJson("{$url}/start", ['employee_id' => $employee->id])->assertOk();
    bearer($actor)->postJson("{$url}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::INSPECTING);

    bearer($actor)->postJson("{$url}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('message', '需检验工序尚未检验通过，不能提交下道');

    bearer($actor)->postJson("{$url}/inspect", ['passed' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason', 'data.errors');

    bearer($actor)->postJson("{$url}/inspect", ['passed' => false, 'reason' => '焊点虚焊'])
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::REWORKING)
        ->assertJsonPath('data.rework_reason', '焊点虚焊')
        ->assertJsonPath('data.rework_count', 1);

    bearer($actor)->postJson("{$url}/start", ['employee_id' => $employee->id])->assertOk();
    bearer($actor)->postJson("{$url}/complete")->assertOk();

    bearer($actor)->postJson("{$url}/inspect", ['passed' => true])
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::INSPECTED)
        ->assertJsonPath('data.status_label', '检验通过');
    bearer($actor)->postJson("{$url}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::PENDING_TRANSFER);
    bearer($actor)->postJson("{$url}/receive")
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::TRANSFERRED)
        ->assertJsonPath('data.status_label', '已流转');

    $record->refresh();

    expect($record->inspected_at)->not->toBeNull()
        ->and($record->submitted_at)->not->toBeNull()
        ->and($record->received_at)->not->toBeNull();
});

it('records an exception only when a reason is given', function () {
    [, $records, $employee] = flowTask([false]);
    $actor = userWithPermissions([
        'production.flows.start',
        'production.flows.exception',
    ]);
    $record = $records->first();
    $url = "/api/v1/production/process-records/{$record->id}";

    bearer($actor)->postJson("{$url}/start", ['employee_id' => $employee->id])->assertOk();

    bearer($actor)->postJson("{$url}/exception", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason', 'data.errors');

    bearer($actor)->postJson("{$url}/exception", ['reason' => '设备温度失控'])
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::REWORKING)
        ->assertJsonPath('data.exception_reason', '设备温度失控');

    $record->refresh();

    expect($record->exception_at)->not->toBeNull()
        ->and($record->rework_count)->toBe(0);

    bearer($actor)->postJson("{$url}/start", ['employee_id' => $employee->id])
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::PROCESSING);
});

it('refuses an exception on an operation that has not started', function () {
    [, $records, $employee] = flowTask([false, false]);
    $actor = userWithPermissions([
        'production.flows.start',
        'production.flows.exception',
    ]);
    $second = $records[1];

    bearer($actor)->postJson("/api/v1/production/process-records/{$second->id}/exception", [
        'reason' => '想跳过首道工序',
    ])->assertUnprocessable()
        ->assertJsonPath('message', '工序当前状态为「待加工」，不能上报异常');

    bearer($actor)->postJson("/api/v1/production/process-records/{$second->id}/start", [
        'employee_id' => $employee->id,
    ])->assertUnprocessable()
        ->assertJsonPath('message', '上道工序尚未流转，不能开始本道工序');
});

it('records a rework reason and counts each rework', function () {
    [, $records, $employee] = flowTask([false]);
    $actor = userWithPermissions([
        'production.flows.start',
        'production.flows.rework',
    ]);
    $record = $records->first();
    $url = "/api/v1/production/process-records/{$record->id}";

    bearer($actor)->postJson("{$url}/start", ['employee_id' => $employee->id])->assertOk();

    bearer($actor)->postJson("{$url}/rework", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason', 'data.errors');

    bearer($actor)->postJson("{$url}/rework", ['reason' => '尺寸超差'])
        ->assertOk()
        ->assertJsonPath('data.status', ProcessRecord::REWORKING)
        ->assertJsonPath('data.rework_reason', '尺寸超差')
        ->assertJsonPath('data.rework_count', 1);

    $record->refresh();

    expect($record->rework_at)->not->toBeNull();
});

it('refuses an operator that does not exist or was disabled', function () {
    [, $records, $employee] = flowTask([false]);
    $actor = userWithPermissions(['production.flows.start']);
    $url = "/api/v1/production/process-records/{$records->first()->id}/start";

    bearer($actor)->postJson($url)->assertUnprocessable()
        ->assertJsonPath('message', '工序执行必须指定操作人员');

    $employee->forceFill(['status' => false])->save();

    bearer($actor)->postJson($url, ['employee_id' => $employee->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', '操作人员不存在或已停用，不能开始工序');
});

it('lists executions filtered by task, order, status and SN', function () {
    [$task, $records] = flowTask([false, false]);
    [$otherTask, $otherRecords] = flowTask([false]);
    $matched = $records->first();
    $matched->update(['sn' => 'SN-FLOW-001']);
    $otherRecords->first()->update(['sn' => 'SN-FLOW-002']);
    $actor = userWithPermissions(['production.flows.index']);

    bearer($actor)->getJson('/api/v1/production/process-records?'.http_build_query([
        'production_task_id' => $task->id,
        'production_order_id' => $task->production_order_id,
        'status' => ProcessRecord::PENDING,
        'sn' => 'SN-FLOW-001',
    ]))->assertOk()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.items.0.id', $matched->id)
        ->assertJsonPath('data.items.0.sn', 'SN-FLOW-001')
        ->assertJsonPath('data.items.0.status_label', '待加工')
        ->assertJsonPath('data.items.0.production_task.code', $task->code)
        ->assertJsonPath('data.items.0.production_order.code', $task->productionOrder->code)
        ->assertJsonPath('data.items.0.route_operation.process.name', $matched->routeOperation->process->name);

    bearer($actor)->getJson("/api/v1/production/process-records?production_order_id={$otherTask->production_order_id}")
        ->assertOk()
        ->assertJsonPath('data.meta.total', 1);
});

it('requires the matching permission for every flow endpoint', function () {
    [, $records] = flowTask([false]);
    $record = $records->first();
    $actor = userWithPermissions([]);
    $url = "/api/v1/production/process-records/{$record->id}";

    bearer($actor)->getJson('/api/v1/production/process-records')
        ->assertForbidden()
        ->assertJsonPath('code', 403);

    foreach (['start', 'complete', 'inspect', 'submit', 'receive', 'exception', 'rework'] as $action) {
        bearer($actor)->postJson("{$url}/{$action}")
            ->assertForbidden()
            ->assertJsonPath('code', 403);
    }
});
