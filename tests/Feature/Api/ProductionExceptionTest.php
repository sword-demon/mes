<?php

use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionException;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('reports an exception with an order and evidence images', function () {
    Storage::fake('public');
    $actor = userWithPermissions(['production.exceptions.create'], 'production_exception_reporter');
    $order = ProductionOrder::factory()->create();

    $response = bearer($actor)->post('/api/v1/production/exceptions', [
        'type' => ProductionException::TYPE_EQUIPMENT,
        'production_order_id' => $order->id,
        'description' => '贴片机吸嘴堵塞，产线停线。',
        'images' => [UploadedFile::fake()->image('设备异常.jpg', 20, 20)],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.type', ProductionException::TYPE_EQUIPMENT)
        ->assertJsonPath('data.type_label', '设备异常')
        ->assertJsonPath('data.status', ProductionException::REPORTED)
        ->assertJsonPath('data.status_label', '已上报')
        ->assertJsonPath('data.description', '贴片机吸嘴堵塞，产线停线。')
        ->assertJsonPath('data.production_order.id', $order->id)
        ->assertJsonPath('data.production_order.code', $order->code)
        ->assertJsonPath('data.process_record', null)
        ->assertJsonPath('data.reporter.name', $actor->name)
        ->assertJsonPath('data.images.0.original_name', '设备异常.jpg')
        ->assertJsonPath('data.images.0.url', fn (string $url): bool => str_contains($url, '/storage/production-exception-images/'));

    $exception = ProductionException::query()->sole();
    $image = $exception->images()->sole();

    expect($exception->code)->toStartWith('EXC-')
        ->and($exception->reported_at)->not->toBeNull();
    Storage::disk('public')->assertExists($image->path);
});

it('derives the order from a process execution record', function () {
    $actor = userWithPermissions(['production.exceptions.create'], 'production_exception_record_reporter');
    $record = ProcessRecord::factory()->create(['sn' => 'MES20261116000001']);
    $orderId = $record->productionTask->production_order_id;

    bearer($actor)->postJson('/api/v1/production/exceptions', [
        'type' => ProductionException::TYPE_PROCESS,
        'process_record_id' => $record->id,
        'description' => '首件尺寸超出工艺公差。',
    ])
        ->assertOk()
        ->assertJsonPath('data.production_order.id', $orderId)
        ->assertJsonPath('data.process_record.id', $record->id)
        ->assertJsonPath('data.process_record.sn', 'MES20261116000001')
        ->assertJsonPath('data.process_record.route_operation.process.name', $record->routeOperation->process->name);
});

it('rejects a process record that belongs to another order', function () {
    $actor = userWithPermissions(['production.exceptions.create'], 'production_exception_mismatch_reporter');
    $order = ProductionOrder::factory()->create();
    $record = ProcessRecord::factory()->create();

    bearer($actor)->postJson('/api/v1/production/exceptions', [
        'type' => ProductionException::TYPE_MATERIAL,
        'production_order_id' => $order->id,
        'process_record_id' => $record->id,
        'description' => '物料批次错误。',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', '工序执行记录不属于该生产订单');
});

it('validates the exception report payload', function () {
    $actor = userWithPermissions(['production.exceptions.create'], 'production_exception_validator');
    $order = ProductionOrder::factory()->create();

    bearer($actor)->postJson('/api/v1/production/exceptions', [
        'type' => 'unknown',
        'description' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.description.0', '请填写异常描述')
        ->assertJsonPath('data.errors.production_order_id.0', '请关联生产订单或工序执行记录')
        ->assertJsonValidationErrors(['type', 'description', 'production_order_id', 'process_record_id'], 'data.errors');

    bearer($actor)->post('/api/v1/production/exceptions', [
        'type' => ProductionException::TYPE_QUALITY,
        'production_order_id' => $order->id,
        'description' => '外观划伤。',
        'images' => [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images.0', 'data.errors');

    bearer($actor)->post('/api/v1/production/exceptions', [
        'type' => ProductionException::TYPE_QUALITY,
        'production_order_id' => $order->id,
        'description' => '外观划伤。',
        'images' => array_map(
            fn (int $index) => UploadedFile::fake()->image("e{$index}.jpg", 10, 10),
            range(1, 6),
        ),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('images', 'data.errors');
});

it('lists exceptions filtered by type, status, order and report date', function () {
    $actor = userWithPermissions(['production.exceptions.index'], 'production_exception_reader');
    $order = ProductionOrder::factory()->create();
    $otherOrder = ProductionOrder::factory()->create();

    ProductionException::factory()->create([
        'code' => 'EXC-MATCH-LATE',
        'type' => ProductionException::TYPE_EQUIPMENT,
        'status' => ProductionException::PROCESSING,
        'production_order_id' => $order->id,
        'reported_at' => '2026-11-16 10:00:00',
    ]);
    ProductionException::factory()->create([
        'code' => 'EXC-MATCH-EARLY',
        'type' => ProductionException::TYPE_EQUIPMENT,
        'status' => ProductionException::PROCESSING,
        'production_order_id' => $order->id,
        'reported_at' => '2026-11-16 09:00:00',
    ]);
    ProductionException::factory()->create([
        'type' => ProductionException::TYPE_QUALITY,
        'status' => ProductionException::PROCESSING,
        'production_order_id' => $order->id,
        'reported_at' => '2026-11-16 11:00:00',
    ]);
    ProductionException::factory()->create([
        'type' => ProductionException::TYPE_EQUIPMENT,
        'status' => ProductionException::REPORTED,
        'production_order_id' => $order->id,
        'reported_at' => '2026-11-16 11:30:00',
    ]);
    ProductionException::factory()->create([
        'type' => ProductionException::TYPE_EQUIPMENT,
        'status' => ProductionException::PROCESSING,
        'production_order_id' => $otherOrder->id,
        'reported_at' => '2026-11-16 12:00:00',
    ]);
    ProductionException::factory()->create([
        'type' => ProductionException::TYPE_EQUIPMENT,
        'status' => ProductionException::PROCESSING,
        'production_order_id' => $order->id,
        'reported_at' => '2026-11-15 12:00:00',
    ]);

    bearer($actor)->getJson('/api/v1/production/exceptions?'.http_build_query([
        'type' => ProductionException::TYPE_EQUIPMENT,
        'status' => ProductionException::PROCESSING,
        'production_order_id' => $order->id,
        'start_date' => '2026-11-16',
        'end_date' => '2026-11-16',
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.code', 'EXC-MATCH-LATE')
        ->assertJsonPath('data.items.1.code', 'EXC-MATCH-EARLY')
        ->assertJsonPath('data.items.0.type_label', '设备异常')
        ->assertJsonPath('data.items.0.status_label', '处理中')
        ->assertJsonPath('data.items.0.production_order.code', $order->code);
});

it('filters report dates by the Shanghai business day', function () {
    $actor = userWithPermissions(['production.exceptions.index'], 'production_exception_timezone_reader');
    $order = ProductionOrder::factory()->create();

    foreach ([
        'EXC-SHANGHAI-START' => '2026-11-15 16:00:00',
        'EXC-SHANGHAI-END' => '2026-11-16 15:59:59',
        'EXC-UTC-PREVIOUS' => '2026-11-15 15:59:59',
        'EXC-UTC-NEXT' => '2026-11-16 16:00:00',
    ] as $code => $reportedAt) {
        ProductionException::factory()->create([
            'code' => $code,
            'production_order_id' => $order->id,
            'reported_at' => $reportedAt,
        ]);
    }

    bearer($actor)->getJson('/api/v1/production/exceptions?start_date=2026-11-16&end_date=2026-11-16')
        ->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.code', 'EXC-SHANGHAI-END')
        ->assertJsonPath('data.items.1.code', 'EXC-SHANGHAI-START');
});

it('processes and closes an exception while enforcing the state machine', function () {
    $actor = userWithPermissions([
        'production.exceptions.process',
        'production.exceptions.close',
    ], 'production_exception_handler');
    $exception = ProductionException::factory()->create(['status' => ProductionException::REPORTED]);
    $processing = ProductionException::factory()->create([
        'status' => ProductionException::PROCESSING,
        'handling_result' => '已定位异常原因。',
    ]);
    $closed = ProductionException::factory()->create(['status' => ProductionException::CLOSED]);

    bearer($actor)->postJson("/api/v1/production/exceptions/{$exception->id}/process")
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.handling_result.0', '请填写处理结果');

    bearer($actor)->postJson("/api/v1/production/exceptions/{$exception->id}/process", [
        'handling_result' => '   ',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', '请填写处理结果');

    bearer($actor)->postJson("/api/v1/production/exceptions/{$processing->id}/process", [
        'handling_result' => '重复处理',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有已上报的生产异常可以处理');

    bearer($actor)->postJson("/api/v1/production/exceptions/{$processing->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', ProductionException::CLOSED)
        ->assertJsonPath('data.status_label', '已关闭')
        ->assertJsonPath('data.handling_result', '已定位异常原因。')
        ->assertJsonPath('data.closed_at', fn (string $value): bool => $value !== '');

    bearer($actor)->postJson("/api/v1/production/exceptions/{$closed->id}/close")
        ->assertUnprocessable()
        ->assertJsonPath('message', '只有处理中的生产异常可以关闭');

    bearer($actor)->postJson("/api/v1/production/exceptions/{$exception->id}/process", [
        'handling_result' => '已更换吸嘴并复测。',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', ProductionException::PROCESSING)
        ->assertJsonPath('data.handling_result', '已更换吸嘴并复测。')
        ->assertJsonPath('data.handler.name', $actor->name)
        ->assertJsonPath('data.handled_at', fn (string $value): bool => $value !== '');

    bearer($actor)->postJson("/api/v1/production/exceptions/{$exception->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', ProductionException::CLOSED)
        ->assertJsonPath('data.handling_result', '已更换吸嘴并复测。');

    $exception->refresh();

    expect($exception->handled_at)->not->toBeNull()
        ->and($exception->closed_at)->not->toBeNull();
});

it('refuses to close a processing exception without a handling result', function () {
    $actor = userWithPermissions(['production.exceptions.close'], 'production_exception_close_guard');
    $exception = ProductionException::factory()->create([
        'status' => ProductionException::PROCESSING,
        'handling_result' => null,
    ]);

    bearer($actor)->postJson("/api/v1/production/exceptions/{$exception->id}/close")
        ->assertUnprocessable()
        ->assertJsonPath('message', '关闭生产异常前必须填写处理结果');

    expect($exception->fresh()->status)->toBe(ProductionException::PROCESSING);
});

it('offers orders and their process records for the report form', function () {
    $actor = userWithPermissions([
        'production.exceptions.index',
        'production.exceptions.create',
    ], 'production_exception_option_reader');
    $order = ProductionOrder::factory()->create();
    $task = ProductionTask::factory()->create(['production_order_id' => $order->id]);
    $record = ProcessRecord::factory()->create([
        'production_task_id' => $task->id,
        'sn' => 'MES20261116000002',
    ]);

    bearer($actor)->getJson("/api/v1/production/exceptions/options?production_order_id={$order->id}")
        ->assertOk()
        ->assertJsonPath('data.orders.0.id', $order->id)
        ->assertJsonPath('data.orders.0.code', $order->code)
        ->assertJsonPath('data.process_records.0.id', $record->id)
        ->assertJsonPath('data.process_records.0.sn', 'MES20261116000002')
        ->assertJsonPath('data.process_records.0.production_order.code', $order->code)
        ->assertJsonPath('data.process_records.0.route_operation.process.name', $record->routeOperation->process->name);

    bearer($actor)->getJson('/api/v1/production/exceptions/options')
        ->assertOk()
        ->assertJsonPath('data.process_records.0.id', $record->id)
        ->assertJsonPath('data.process_records.0.production_order.code', $order->code);

    bearer($actor)->getJson("/api/v1/production/exceptions/options?production_order_id={$order->id}&include=orders")
        ->assertOk()
        ->assertJsonCount(1, 'data.orders')
        ->assertJsonCount(0, 'data.process_records');

    bearer($actor)->getJson("/api/v1/production/exceptions/options?production_order_id={$order->id}&include=process_records")
        ->assertOk()
        ->assertJsonCount(0, 'data.orders')
        ->assertJsonPath('data.process_records.0.id', $record->id);
});

it('searches report order options by keyword', function () {
    $actor = userWithPermissions(['production.exceptions.create'], 'production_exception_option_search');
    $old = ProductionOrder::factory()->create(['code' => 'PO-OLD-999']);
    ProductionOrder::factory()->create(['code' => 'PO-NEW-001']);

    bearer($actor)->getJson('/api/v1/production/exceptions/options?keyword=OLD')
        ->assertOk()
        ->assertJsonCount(1, 'data.orders')
        ->assertJsonPath('data.orders.0.id', $old->id)
        ->assertJsonPath('data.orders.0.code', 'PO-OLD-999');
});

it('treats zero as a meaningful order search keyword', function () {
    $actor = userWithPermissions(['production.exceptions.create'], 'production_exception_zero_search');
    $zero = ProductionOrder::factory()->create(['code' => 'PO-ZERO-0']);
    ProductionOrder::factory()->create(['code' => 'PO-NINE-9']);

    bearer($actor)->getJson('/api/v1/production/exceptions/options?keyword=0')
        ->assertOk()
        ->assertJsonCount(1, 'data.orders')
        ->assertJsonPath('data.orders.0.id', $zero->id);
});

it('validates exception list filters', function (string $query, string $field) {
    bearer(userWithPermissions(['production.exceptions.index'], 'production_exception_invalid_'.md5($query)))
        ->getJson('/api/v1/production/exceptions?'.$query)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field], 'data.errors');
})->with([
    'unknown type' => ['type=unknown', 'type'],
    'unknown status' => ['status=open', 'status'],
    'invalid start date' => ['start_date=2026-13-01', 'start_date'],
    'end before start' => ['start_date=2026-11-16&end_date=2026-11-15', 'end_date'],
]);

it('requires permissions for exception endpoints', function (string $method, string $uri) {
    $exception = ProductionException::factory()->create();

    bearer(userWithPermissions([], 'production_exception_denied_'.md5($method.$uri)))
        ->json($method, str_replace('{exception}', (string) $exception->id, $uri))
        ->assertForbidden()
        ->assertJsonPath('code', 403);
})->with([
    'list' => ['GET', '/api/v1/production/exceptions'],
    'options' => ['GET', '/api/v1/production/exceptions/options'],
    'report' => ['POST', '/api/v1/production/exceptions'],
    'process' => ['POST', '/api/v1/production/exceptions/{exception}/process'],
    'close' => ['POST', '/api/v1/production/exceptions/{exception}/close'],
]);

it('requires authentication for exception endpoints', function (string $method, string $uri) {
    $this->json($method, $uri)
        ->assertUnauthorized()
        ->assertJsonPath('code', 401);
})->with([
    'list' => ['GET', '/api/v1/production/exceptions'],
    'options' => ['GET', '/api/v1/production/exceptions/options'],
    'report' => ['POST', '/api/v1/production/exceptions'],
    'process' => ['POST', '/api/v1/production/exceptions/1/process'],
    'close' => ['POST', '/api/v1/production/exceptions/1/close'],
]);
