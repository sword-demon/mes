<?php

use App\Modules\Organization\Model\Employee;
use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Service\ProcessRecordService;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\System\Model\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A released order with one task and one execution record awaiting inspection.
 *
 * @return array{0: ProductionOrder, 1: ProcessRecord, 2: RouteOperation}
 */
function inspectionCase(?string $recordStatus = ProcessRecord::INSPECTING): array
{
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::IN_PROGRESS]);
    $task = ProductionTask::factory()->create(['production_order_id' => $order->id]);
    $operation = RouteOperation::factory()->create([
        'route_id' => $order->route_id,
        'process_id' => Process::factory()->create(['requires_inspection' => true])->id,
        'sequence' => 1,
    ]);
    $record = ProcessRecord::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $operation->id,
        'sequence' => 1,
        'status' => $recordStatus,
        'completed_at' => $recordStatus === ProcessRecord::INSPECTING ? now()->subMinute() : null,
    ]);

    return [$order, $record, $operation];
}

function inspectionActor(): User
{
    return userWithPermissions([
        'quality.inspections.index',
        'quality.inspections.create',
        'quality.inspections.record',
    ]);
}

/** @param array<string, mixed> $data */
function createInspectionTask(User $actor, array $data): int
{
    return (int) bearer($actor)->postJson('/api/v1/quality/inspection-tasks', $data)
        ->assertOk()
        ->json('data.id');
}

it('creates an IPQC task for a process record awaiting inspection', function () {
    [$order, $record] = inspectionCase();
    InspectionItem::factory()->create();
    $actor = inspectionActor();

    bearer($actor)->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
        'remark' => '首件检验',
    ])->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.result', null)
        ->assertJsonPath('data.result_label', '待检验')
        ->assertJsonPath('data.production_order.code', $order->code)
        ->assertJsonPath('data.process_record.id', $record->id)
        ->assertJsonPath('data.route_operation.id', $record->route_operation_id);

    expect(InspectionTask::query()->sole()->inspection_type)->toBe(InspectionTask::IPQC);
});

it('creates an OQC task at order level without a process record', function () {
    [$order, , $operation] = inspectionCase();
    [, , $otherOperation] = inspectionCase();
    InspectionItem::factory()->create();
    $actor = inspectionActor();

    bearer($actor)->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $order->id,
        'inspection_type' => 'oqc',
        'route_operation_id' => $operation->id,
    ])->assertOk()
        ->assertJsonPath('data.inspection_type', 'oqc')
        ->assertJsonPath('data.process_record', null)
        ->assertJsonPath('data.route_operation.id', $operation->id);

    bearer($actor)->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $order->id,
        'inspection_type' => 'oqc',
    ])->assertUnprocessable()->assertJsonPath('message', 'OQC 检验必须关联该订单工艺路线中的工序');

    bearer($actor)->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $order->id,
        'inspection_type' => 'oqc',
        'route_operation_id' => $otherOperation->id,
    ])->assertUnprocessable()->assertJsonPath('message', 'OQC 检验必须关联该订单工艺路线中的工序');
});

it('rejects an IPQC task without a record or with a record outside the order or gate', function () {
    [$order, $record] = inspectionCase();
    [$otherOrder] = inspectionCase();
    $actor = inspectionActor();
    $url = '/api/v1/quality/inspection-tasks';

    bearer($actor)->postJson($url, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
    ])->assertUnprocessable()->assertJsonPath('message', 'IPQC 检验必须关联待检验工序');

    bearer($actor)->postJson($url, [
        'production_order_id' => $otherOrder->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ])->assertUnprocessable()->assertJsonPath('message', '工序不属于该生产订单');

    [$processingOrder, $processingRecord] = inspectionCase(ProcessRecord::PROCESSING);

    bearer($actor)->postJson($url, [
        'production_order_id' => $processingOrder->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $processingRecord->id,
    ])->assertUnprocessable()->assertJsonPath('message', '工序当前状态不是待检验，不能创建 IPQC 任务');

    bearer($actor)->postJson($url, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
        'route_operation_id' => $record->route_operation_id,
    ])->assertUnprocessable()->assertJsonValidationErrors('route_operation_id', 'data.errors');
});

it('judges boundary values inclusively and releases the process record', function () {
    [$order, $record] = inspectionCase();
    $low = InspectionItem::factory()->create(['code' => 'LOW', 'name' => '下限值']);
    $high = InspectionItem::factory()->create(['code' => 'HIGH', 'name' => '上限值']);
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
        'remark' => '首件检验',
    ]);

    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$id}/record", [
        'items' => [
            ['inspection_item_id' => $low->id, 'measured_value' => '4.8'],
            ['inspection_item_id' => $high->id, 'measured_value' => '5.2'],
        ],
    ])->assertOk()
        ->assertJsonPath('data.status', 'passed')
        ->assertJsonPath('data.result_label', '合格')
        ->assertJsonPath('data.inspector.id', $actor->id)
        ->assertJsonPath('data.remark', '首件检验')
        ->assertJsonPath('data.items.0.measured_value', '4.800')
        ->assertJsonPath('data.items.0.min_value', '4.800')
        ->assertJsonPath('data.items.0.max_value', '5.200')
        ->assertJsonPath('data.items.0.passed', true)
        ->assertJsonPath('data.items.1.passed', true);

    $task = InspectionTask::query()->findOrFail($id);
    $record->refresh();

    expect($task->inspector_id)->toBe($actor->id)
        ->and($task->inspected_at)->not->toBeNull()
        ->and($record->status)->toBe(ProcessRecord::INSPECTED)
        ->and($record->inspected_at)->not->toBeNull();
});

it('fails the whole task on one out-of-range value and sends the process back to rework', function () {
    [$order, $record] = inspectionCase();
    $item = InspectionItem::factory()->create(['code' => 'TEMP', 'name' => '工作温度']);
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);
    $url = "/api/v1/quality/inspection-tasks/{$id}/record";

    bearer($actor)->postJson($url, [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => '5.201']],
        'remark' => '超温',
    ])->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.result_label', '不合格')
        ->assertJsonPath('data.items.0.passed', false);

    $record->refresh();

    expect($record->status)->toBe(ProcessRecord::REWORKING)
        ->and($record->rework_reason)->toBe('超温')
        ->and($record->rework_count)->toBe(1);

    bearer($actor)->postJson($url, [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => '5']],
    ])->assertUnprocessable()->assertJsonPath('message', '检验任务已判定，不能重复提交');
});

it('allows re-inspection of the same operation after rework', function () {
    [$order, $record] = inspectionCase();
    $item = InspectionItem::factory()->create(['code' => 'WELD', 'name' => '焊点强度']);
    $actor = inspectionActor();

    $first = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);
    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$first}/record", [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => '5.201']],
        'remark' => '焊点虚焊',
    ])->assertOk()->assertJsonPath('data.status', 'failed');

    $employee = Employee::factory()->create();
    app(ProcessRecordService::class)->start($record->fresh(), ['employee_id' => $employee->id]);
    app(ProcessRecordService::class)->complete($record->fresh());

    $second = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);
    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$second}/record", [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => '5']],
    ])->assertOk()->assertJsonPath('data.status', 'passed');

    expect(InspectionTask::query()->where('process_record_id', $record->id)->count())->toBe(2)
        ->and($record->fresh()->status)->toBe(ProcessRecord::INSPECTED);
});

it('derives a rework reason from the failed measurements', function () {
    [$order, $record] = inspectionCase();
    $item = InspectionItem::factory()->create(['code' => 'TEMP', 'name' => '工作温度']);
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);

    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$id}/record", [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => 5.3]],
    ])->assertOk()->assertJsonPath('data.status', 'failed');

    expect($record->fresh()->rework_reason)
        ->toContain('检验不合格：工作温度=5.300')
        ->toContain('4.800~5.200');
});

it('rejects a stale pending task after the process enters a new inspection cycle', function () {
    [$order, $record] = inspectionCase();
    $item = InspectionItem::factory()->create(['code' => 'STALE', 'name' => '焊点强度']);
    $actor = inspectionActor();
    $stale = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);
    $employee = Employee::factory()->create();

    app(ProcessRecordService::class)->exception($record, '设备异常');
    app(ProcessRecordService::class)->start($record->fresh(), ['employee_id' => $employee->id]);
    app(ProcessRecordService::class)->complete($record->fresh());

    createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);

    bearer($actor)->getJson('/api/v1/quality/inspection-tasks?result=pending')
        ->assertOk()
        ->assertJsonPath('data.items.0.status', 'expired')
        ->assertJsonPath('data.items.0.result_label', '已过期');

    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$stale}/record", [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => 5]],
    ])->assertUnprocessable()->assertJsonPath('message', '检验任务已过期，请按当前工序轮次重新检验');
});

it('snapshots the enabled inspection items when the task is created', function () {
    [$order, $record] = inspectionCase();
    $first = InspectionItem::factory()->create(['code' => 'FIRST', 'name' => '首项']);
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);

    InspectionItem::factory()->create(['code' => 'LATE', 'name' => '后加项']);

    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$id}/record", [
        'items' => [['inspection_item_id' => $first->id, 'measured_value' => 5]],
    ])->assertOk()
        ->assertJsonPath('data.status', 'passed')
        ->assertJsonCount(1, 'data.required_items')
        ->assertJsonPath('data.required_items.0.name', '首项');
});

it('refuses to create a task when no inspection item is enabled', function () {
    [$order, $record] = inspectionCase();

    bearer(inspectionActor())->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ])->assertUnprocessable()->assertJsonPath('message', '没有启用的检验项目，不能创建检验任务');
});

it('rejects missing items and duplicate measurements', function () {
    [$order, $record] = inspectionCase();
    $item = InspectionItem::factory()->create();
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);
    $url = "/api/v1/quality/inspection-tasks/{$id}/record";

    bearer($actor)->postJson($url, ['items' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('items', 'data.errors');

    bearer($actor)->postJson($url, ['items' => [
        ['inspection_item_id' => $item->id, 'measured_value' => 5],
        ['inspection_item_id' => $item->id, 'measured_value' => 6],
    ]])->assertUnprocessable()->assertJsonValidationErrors('items.1.inspection_item_id', 'data.errors');
});

it('refuses to create a task when an enabled item has no standard', function () {
    [$order, $record] = inspectionCase();
    $item = InspectionItem::factory()->create();
    $item->standard()->delete();

    bearer(inspectionActor())->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ])->assertUnprocessable()->assertJsonPath('message', "检验项目「{$item->name}」未维护标准，不能创建检验任务");
});

it('refuses to judge when a required item lost its standard', function () {
    [$order, $record] = inspectionCase();
    $item = InspectionItem::factory()->create();
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);
    $item->standard()->delete();

    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$id}/record", ['items' => [
        ['inspection_item_id' => $item->id, 'measured_value' => 5],
    ]])->assertUnprocessable()->assertJsonPath('message', "检验项目「{$item->name}」未维护标准，不能判定");
});

it('requires every enabled inspection item to be measured', function () {
    [$order, $record] = inspectionCase();
    $first = InspectionItem::factory()->create(['code' => 'A', 'name' => '长度']);
    $second = InspectionItem::factory()->create(['code' => 'B', 'name' => '宽度']);
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);

    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$id}/record", [
        'items' => [['inspection_item_id' => $first->id, 'measured_value' => 5]],
    ])->assertUnprocessable()->assertJsonPath('message', '检验项目「宽度」未录入实测值');

    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$id}/record", [
        'items' => [
            ['inspection_item_id' => $first->id, 'measured_value' => 5],
            ['inspection_item_id' => $second->id, 'measured_value' => 5],
        ],
    ])->assertOk()->assertJsonPath('data.status', 'passed');
});

it('blocks a second pending task for the same inspection cycle', function () {
    [$order, $record] = inspectionCase();
    InspectionItem::factory()->create();
    $actor = inspectionActor();
    $payload = [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ];

    createInspectionTask($actor, $payload);

    bearer($actor)->postJson('/api/v1/quality/inspection-tasks', $payload)
        ->assertUnprocessable()->assertJsonPath('message', '该工序已有待判定的检验任务');
});

it('rejects an OQC task tied to a process execution', function () {
    [$order, $record] = inspectionCase();

    bearer(inspectionActor())->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $order->id,
        'inspection_type' => 'oqc',
        'process_record_id' => $record->id,
    ])->assertUnprocessable()->assertJsonPath('message', 'OQC 检验不关联工序执行记录');
});

it('filters tasks and records by order, operation, result and time', function () {
    // Pin the clock so the UTC date and the Shanghai business day stay aligned.
    $this->travelTo('2026-11-16 04:00:00');

    [$order, $record] = inspectionCase();
    $secondOperation = RouteOperation::factory()->create([
        'route_id' => $order->route_id,
        'process_id' => Process::factory()->create(['requires_inspection' => true])->id,
        'sequence' => 2,
    ]);
    $secondRecord = ProcessRecord::factory()->create([
        'production_task_id' => $record->production_task_id,
        'route_operation_id' => $secondOperation->id,
        'sequence' => 2,
        'status' => ProcessRecord::INSPECTING,
    ]);
    $actor = inspectionActor();
    $item = InspectionItem::factory()->create();

    $pendingId = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);
    $passedId = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $secondRecord->id,
    ]);
    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$passedId}/record", [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => 5]],
    ])->assertOk();

    InspectionTask::query()->whereKey($pendingId)->update(['created_at' => now()->subDays(2)]);

    $base = '/api/v1/quality/inspection-tasks?';

    bearer($actor)->getJson($base.http_build_query(['production_order_id' => $order->id]))
        ->assertOk()->assertJsonCount(2, 'data.items');
    bearer($actor)->getJson($base.http_build_query(['route_operation_id' => $secondOperation->id]))
        ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $passedId);
    bearer($actor)->getJson($base.http_build_query(['result' => 'passed']))
        ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.status', 'passed');
    bearer($actor)->getJson($base.http_build_query(['result' => 'pending']))
        ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.status', 'pending');
    bearer($actor)->getJson($base.http_build_query(['judged' => 1]))
        ->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $passedId);
    bearer($actor)->getJson($base.http_build_query([
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]))->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $passedId);
    bearer($actor)->getJson($base.http_build_query([
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->subDays(2)->toDateString(),
    ]))->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $pendingId);
    bearer($actor)->getJson($base.http_build_query(['keyword' => $order->code]))
        ->assertOk()->assertJsonCount(2, 'data.items');
    bearer($actor)->getJson($base.http_build_query(['keyword' => 'NOT-EXISTS']))
        ->assertOk()->assertJsonCount(0, 'data.items');
});

it('records an OQC judgement without touching a process record', function () {
    [$order, $record, $operation] = inspectionCase();
    $item = InspectionItem::factory()->create();
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'oqc',
        'route_operation_id' => $operation->id,
    ]);

    bearer($actor)->postJson("/api/v1/quality/inspection-tasks/{$id}/record", [
        'items' => [['inspection_item_id' => $item->id, 'measured_value' => 5.3]],
        'remark' => '出货超差',
    ])->assertOk()->assertJsonPath('data.status', 'failed');

    expect($record->fresh()->status)->toBe(ProcessRecord::INSPECTING);
});

it('uploads inspection images', function () {
    Storage::fake('public');
    [$order, , $operation] = inspectionCase();
    InspectionItem::factory()->create();
    $actor = inspectionActor();
    $id = createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'oqc',
        'route_operation_id' => $operation->id,
    ]);
    $url = "/api/v1/quality/inspection-tasks/{$id}";

    bearer($actor)->post("{$url}/images", [])->assertUnprocessable()
        ->assertJsonValidationErrors('images', 'data.errors');
    bearer($actor)->post("{$url}/images", [
        'images' => array_map(fn (int $index) => UploadedFile::fake()->image("e{$index}.jpg", 10, 10), range(1, 6)),
    ])->assertUnprocessable()->assertJsonValidationErrors('images', 'data.errors');

    bearer($actor)->post("{$url}/images", [
        'images' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
    ])->assertUnprocessable()->assertJsonValidationErrors('images.0', 'data.errors');

    bearer($actor)->post("{$url}/images", [
        'images' => [UploadedFile::fake()->image('evidence.jpg', 20, 20)],
    ])->assertOk()
        ->assertJsonPath('data.images.0.original_name', 'evidence.jpg')
        ->assertJsonPath('data.images.0.url', fn (string $value): bool => str_contains($value, '/storage/inspection-images/'));

    $image = InspectionTask::query()->findOrFail($id)->images()->sole();
    Storage::disk('public')->assertExists($image->path);
});

it('returns a task with no operation instead of failing the list', function () {
    $order = ProductionOrder::factory()->create();
    InspectionTask::factory()->create([
        'production_order_id' => $order->id,
        'inspection_type' => InspectionTask::OQC,
        'route_operation_id' => null,
    ]);

    bearer(userWithPermissions(['quality.inspections.index']))->getJson('/api/v1/quality/inspection-tasks')
        ->assertOk()->assertJsonPath('data.items.0.route_operation', null);
});

it('offers orders and inspectable process records for the create form', function () {
    [$order, $record] = inspectionCase();
    InspectionItem::factory()->create();
    $actor = inspectionActor();

    $url = '/api/v1/quality/inspection-tasks/options?';

    bearer($actor)->getJson($url.http_build_query([
        'production_order_id' => $order->id,
    ]))->assertOk()
        ->assertJsonPath('data.orders.0.id', $order->id)
        ->assertJsonPath('data.process_records.0.id', $record->id)
        ->assertJsonPath('data.process_records.0.route_operation.process.name', $record->routeOperation->process->name)
        ->assertJsonPath('data.operations.0.id', $record->route_operation_id);

    createInspectionTask($actor, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);

    bearer($actor)->getJson($url.http_build_query(['production_order_id' => $order->id]))
        ->assertOk()
        ->assertJsonCount(0, 'data.process_records')
        ->assertJsonPath('data.operations.0.id', $record->route_operation_id);

    ProductionOrder::factory()->create(['code' => 'PO-SEARCH-999']);

    bearer($actor)->getJson($url.http_build_query(['keyword' => 'PO-SEARCH']))
        ->assertOk()
        ->assertJsonCount(1, 'data.orders')
        ->assertJsonPath('data.orders.0.code', 'PO-SEARCH-999');
});

it('protects every inspection endpoint and separates create from record', function () {
    [$order, $record] = inspectionCase();
    InspectionItem::factory()->create();

    $this->getJson('/api/v1/quality/inspection-tasks')->assertUnauthorized();
    $this->getJson('/api/v1/quality/inspection-tasks/options')->assertUnauthorized();
    $this->postJson('/api/v1/quality/inspection-tasks', [])->assertUnauthorized();
    $this->postJson('/api/v1/quality/inspection-tasks/1/record', [])->assertUnauthorized();
    $this->post('/api/v1/quality/inspection-tasks/1/images', [])->assertUnauthorized();

    $creator = userWithPermissions(['quality.inspections.index', 'quality.inspections.create'], 'creator');
    $recorder = userWithPermissions(['quality.inspections.index', 'quality.inspections.record'], 'recorder');
    $taskId = createInspectionTask($creator, [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ]);
    $taskUrl = "/api/v1/quality/inspection-tasks/{$taskId}";

    bearer($creator)->postJson("{$taskUrl}/record", [])->assertForbidden();
    bearer($creator)->post("{$taskUrl}/images", [])->assertForbidden();

    bearer($recorder)->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => $order->id,
        'inspection_type' => 'ipqc',
        'process_record_id' => $record->id,
    ])->assertForbidden();

    $stranger = userWithPermissions([], 'stranger');
    bearer($stranger)->getJson('/api/v1/quality/inspection-tasks')->assertForbidden();
    bearer($stranger)->getJson('/api/v1/quality/inspection-tasks/options')->assertForbidden();
});

it('validates the create payload', function () {
    $actor = inspectionActor();

    bearer($actor)->postJson('/api/v1/quality/inspection-tasks', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['production_order_id', 'inspection_type'], 'data.errors');

    bearer($actor)->postJson('/api/v1/quality/inspection-tasks', [
        'production_order_id' => 999999,
        'inspection_type' => 'fqc',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['production_order_id', 'inspection_type'], 'data.errors');
});
