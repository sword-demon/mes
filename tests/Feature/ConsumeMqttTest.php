<?php

use App\Modules\Equipment\Model\Equipment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;

use function Pest\Laravel\mock;

beforeEach(function () {
    mock(Repository::class)->shouldReceive('countSubscriptions')->andReturn(3);
});

it('consumes the three MQTT topics into history and updates equipment through the command', function () {
    config(['mqtt.factory' => 'F001', 'mqtt.username' => 'consumer', 'mqtt.password' => 'test-password']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01', 'enabled' => false]);
    $callbacks = [];
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once()->with(Mockery::on(fn (ConnectionSettings $settings) => $settings->getUsername() === 'consumer'
        && $settings->getPassword() === 'test-password'
        && $settings->getConnectTimeout() === 5
        && $settings->getKeepAliveInterval() === 30), true);
    foreach (['status', 'data', 'alarm'] as $kind) {
        $client->shouldReceive('subscribe')->once()->with("mes/F001/+/{$kind}", Mockery::type('callable'), 1)
            ->andReturnUsing(function (string $topic, callable $callback) use (&$callbacks, $kind): void {
                $callbacks[$kind] = $callback;
            });
    }
    $client->shouldReceive('loop')->once()->andReturnUsing(function () use (&$callbacks): void {
        foreach (['status', 'data', 'alarm'] as $index => $kind) {
            $callbacks[$kind]("mes/F001/EQ-SMT-01/{$kind}", json_encode([
                'device_code' => 'EQ-SMT-01',
                'timestamp' => '2026-11-16T04:00:0'.$index.'+00:00',
                'status' => 'fault',
                'temperature' => 30,
                'speed' => 0,
                'pressure' => 0,
                'output' => 120,
                'alarm_code' => 'device_fault',
                'message' => '设备故障',
                'level' => 'critical',
            ], JSON_THROW_ON_ERROR));
        }
    });
    $client->shouldReceive('interrupt')->once();
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once();

    $this->artisan('mes:consume-mqtt', ['--max-messages' => 3])->assertSuccessful();

    $this->assertDatabaseCount('mes_equipment_data', 3);
    foreach (['status', 'data', 'alarm'] as $kind) {
        $this->assertDatabaseHas('mes_equipment_data', ['equipment_id' => $equipment->id, 'message_type' => $kind, 'output' => 120]);
    }
    expect($equipment->fresh()->status)->toBe(Equipment::FAULT);
    expect($equipment->fresh()->enabled)->toBeFalse();
});

it('ignores an invalid message and continues consuming the next valid one', function () {
    config(['mqtt.factory' => 'F001']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01']);
    $callbacks = [];
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('subscribe')->times(3)->andReturnUsing(function (string $topic, callable $callback) use (&$callbacks): void {
        $callbacks[] = $callback;
    });
    $client->shouldReceive('loop')->once()->andReturnUsing(function () use (&$callbacks): void {
        $callbacks[0]('mes/F001/EQ-SMT-01/status', '{invalid');
        $callbacks[1]('mes/F001/EQ-SMT-01/data', json_encode([
            'device_code' => 'EQ-SMT-01',
            'timestamp' => '2026-11-16T04:00:00+00:00',
            'status' => 'running',
            'temperature' => 50,
            'speed' => 1000,
            'pressure' => 0.6,
            'output' => 120,
        ], JSON_THROW_ON_ERROR));
    });
    $client->shouldReceive('interrupt')->once();
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once();

    $this->artisan('mes:consume-mqtt', ['--max-messages' => 1])
        ->expectsOutputToContain('忽略无效设备消息')
        ->assertSuccessful();

    $this->assertDatabaseCount('mes_equipment_data', 1);
    expect($equipment->fresh()->status)->toBe(Equipment::RUNNING);
});

it('reports a connection failure and exits unsuccessfully without writing history', function () {
    Exceptions::fake();
    $exception = new ConnectingToBrokerFailedException(1, 'broker unavailable');
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once()->andThrow($exception);
    $client->shouldReceive('isConnected')->once()->andReturnFalse();

    $this->artisan('mes:consume-mqtt')->expectsOutput('MQTT 消费失败，请检查应用日志')->assertFailed();

    Exceptions::assertReported(fn (ConnectingToBrokerFailedException $reported) => $reported->getMessage() === $exception->getMessage());
    $this->assertDatabaseCount('mes_equipment_data', 0);
});

it('stops and reports a persistence failure instead of letting the SDK swallow it', function () {
    Exceptions::fake();
    $equipment = Equipment::factory()->create(['code' => 'EQ-SMT-01']);
    Schema::rename('mes_equipment_data', 'unavailable_equipment_data');
    $callback = null;
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('subscribe')->times(3)->andReturnUsing(function (string $topic, callable $handler) use (&$callback): void {
        $callback = $handler;
    });
    $client->shouldReceive('loop')->once()->andReturnUsing(function () use (&$callback): void {
        $callback('mes/F001/EQ-SMT-01/data', json_encode([
            'device_code' => 'EQ-SMT-01',
            'timestamp' => '2026-11-16T04:00:00+00:00',
            'status' => 'running',
            'temperature' => 50,
            'speed' => 1000,
            'pressure' => 0.6,
            'output' => 120,
        ], JSON_THROW_ON_ERROR));
    });
    $client->shouldReceive('interrupt')->once();
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once();

    try {
        $this->artisan('mes:consume-mqtt')->expectsOutput('MQTT 消费失败，请检查应用日志')->assertFailed();

        Exceptions::assertReported(QueryException::class);
        $this->assertDatabaseCount('unavailable_equipment_data', 0);
        expect($equipment->fresh()->status)->toBe(Equipment::IDLE);
    } finally {
        Schema::rename('unavailable_equipment_data', 'mes_equipment_data');
    }
});

it('rejects an invalid message limit before connecting', function (string $limit) {
    mock(MqttClient::class)->shouldNotReceive('connect');

    $this->artisan('mes:consume-mqtt', ['--max-messages' => $limit])
        ->expectsOutput('--max-messages 必须为非负整数')->assertExitCode(2);
})->with(['-1', 'abc', '1.5']);

it('reports a broken MQTT connection and closes the client', function () {
    Exceptions::fake();
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('subscribe')->times(3);
    $client->shouldReceive('loop')->once()->andThrow(new DataTransferException(1, 'connection lost'));
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once();

    $this->artisan('mes:consume-mqtt')->expectsOutput('MQTT 消费失败，请检查应用日志')->assertFailed();

    Exceptions::assertReported(DataTransferException::class);
    $this->assertDatabaseCount('mes_equipment_data', 0);
});

it('reports a disconnect failure instead of claiming a successful shutdown', function () {
    Exceptions::fake();
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('subscribe')->times(3);
    $client->shouldReceive('loop')->once();
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once()->andThrow(new DataTransferException(1, 'disconnect failed'));

    $this->artisan('mes:consume-mqtt')->expectsOutput('MQTT 断开连接失败，请检查应用日志')->assertFailed();

    Exceptions::assertReported(DataTransferException::class);
});

it('rejects a factory namespace containing MQTT wildcard or topic separators', function (string $factory) {
    config(['mqtt.factory' => $factory]);
    mock(MqttClient::class)->shouldNotReceive('connect');

    $this->artisan('mes:consume-mqtt')
        ->expectsOutput('MQTT_FACTORY 必须为不含 /、+、# 的工厂标识')->assertExitCode(2);
})->with(['', 'bad/factory', '+', '#']);

it('fails when the broker rejects a topic subscription instead of remaining silently idle', function () {
    Exceptions::fake();
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('countSubscriptions')->andReturn(2);
    $repository->shouldReceive('countPendingOutgoingMessages')->andReturn(0);
    $this->app->instance(Repository::class, $repository);
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('subscribe')->times(3);
    $client->shouldNotReceive('loop');
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once();

    $this->artisan('mes:consume-mqtt')->doesntExpectOutputToContain('已订阅')
        ->expectsOutput('MQTT 消费失败，请检查应用日志')->assertFailed();

    Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'MQTT 主题订阅被拒绝');
});

it('waits for acknowledgements before announcing the MQTT subscriptions', function () {
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('countSubscriptions')->twice()->andReturn(0, 3);
    $repository->shouldReceive('countPendingOutgoingMessages')->once()->andReturn(3);
    $this->app->instance(Repository::class, $repository);
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('subscribe')->times(3);
    $client->shouldReceive('loopOnce')->once()->with(Mockery::type('float'), true);
    $client->shouldReceive('loop')->once();
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once();

    $this->artisan('mes:consume-mqtt')->expectsOutputToContain('已订阅')->assertSuccessful();
});

it('fails when MQTT subscription acknowledgement times out', function () {
    $this->freezeTime();
    Exceptions::fake();
    $repository = Mockery::mock(Repository::class);
    $repository->shouldReceive('countSubscriptions')->andReturn(0);
    $repository->shouldReceive('countPendingOutgoingMessages')->andReturn(3);
    $this->app->instance(Repository::class, $repository);
    $client = mock(MqttClient::class);
    $client->shouldReceive('connect')->once();
    $client->shouldReceive('subscribe')->times(3);
    $client->shouldReceive('loopOnce')->once()->andReturnUsing(fn () => $this->travel(6)->seconds());
    $client->shouldNotReceive('loop');
    $client->shouldReceive('isConnected')->once()->andReturnTrue();
    $client->shouldReceive('disconnect')->once();

    $this->artisan('mes:consume-mqtt')->doesntExpectOutputToContain('已订阅')->assertFailed();

    Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'MQTT 主题订阅确认超时');
});
