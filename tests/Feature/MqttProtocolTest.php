<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use Illuminate\Support\Facades\Artisan;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MqttClient as MqttClientContract;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\Repositories\MemoryRepository;
use Symfony\Component\Process\Process;

it('consumes real MQTT packets from EMQX through the Artisan command', function () {
    $factory = 'protocol-'.bin2hex(random_bytes(6));
    config(['mqtt.factory' => $factory]);
    $equipment = Equipment::factory()->create(['code' => 'PROTOCOL-001']);
    $repository = new MemoryRepository;
    $subscriber = new MqttClient(config('mqtt.host'), config('mqtt.port'), 'test-sub-'.$factory, MqttClient::MQTT_3_1_1, $repository);
    $publisher = new MqttClient(config('mqtt.host'), config('mqtt.port'), 'test-pub-'.$factory, MqttClient::MQTT_3_1_1);
    $publisher->connect((new ConnectionSettings)->setConnectTimeout(3), true);
    $this->app->instance(MqttClientContract::class, $subscriber);
    $this->app->instance(Repository::class, $repository);
    $published = false;
    $subscriber->registerLoopEventHandler(function (MqttClient $mqtt, float $elapsed) use ($publisher, $repository, $factory, &$published): void {
        if ($elapsed > 10) {
            $mqtt->interrupt();
        }

        if ($published || $repository->countSubscriptions() !== 3) {
            return;
        }

        foreach (['status', 'data', 'alarm'] as $index => $kind) {
            $publisher->publish("mes/{$factory}/PROTOCOL-001/{$kind}", json_encode([
                'device_code' => 'PROTOCOL-001',
                'timestamp' => '2026-11-16T04:00:0'.$index.'+00:00',
                'status' => 'fault',
                'temperature' => 30,
                'speed' => 0,
                'pressure' => 0,
                'output' => 120,
                'alarm_code' => 'device_fault',
                'message' => '设备故障',
                'level' => 'critical',
            ], JSON_THROW_ON_ERROR), 1);
        }
        $publisher->loop(true, true, 3);
        $published = true;
    });

    try {
        $exitCode = Artisan::call('mes:consume-mqtt', ['--max-messages' => 3]);
    } finally {
        $publisher->disconnect();
    }

    expect($exitCode)->toBe(0);
    $this->assertDatabaseCount('mes_equipment_data', 3);
    expect(EquipmentData::query()->pluck('message_type')->sort()->values()->all())->toBe(['alarm', 'data', 'status']);
    expect($equipment->fresh()->status)->toBe(Equipment::FAULT);
    expect(EquipmentData::query()->where('message_type', 'alarm')->firstOrFail()->alarm['code'])->toBe('device_fault');
})->skip(fn () => getenv('MQTT_PROTOCOL_TESTS') !== '1', 'Set MQTT_PROTOCOL_TESTS=1 with local EMQX running.');

it('ingests the running Compose simulator telemetry for all five ledger devices', function () {
    config(['mqtt.factory' => 'F001']);
    $codes = ['EQ-SMT-01', 'EQ-REFLOW-01', 'EQ-CNC-01', 'EQ-ASM-01', 'EQ-TEST-01'];
    foreach ($codes as $code) {
        Equipment::factory()->create(['code' => $code]);
    }
    $repository = new MemoryRepository;
    $subscriber = new MqttClient(config('mqtt.host'), config('mqtt.port'), 'test-sim-'.bin2hex(random_bytes(6)), MqttClient::MQTT_3_1_1, $repository);
    $this->app->instance(MqttClientContract::class, $subscriber);
    $this->app->instance(Repository::class, $repository);
    $subscriber->registerLoopEventHandler(function (MqttClient $mqtt, float $elapsed): void {
        if ($elapsed > 15) {
            $mqtt->interrupt();
        }
    });

    $exitCode = Artisan::call('mes:consume-mqtt', ['--max-messages' => 15]);

    expect($exitCode)->toBe(0);
    expect(EquipmentData::query()->where('message_type', 'data')->distinct()->count('equipment_id'))->toBe(5);
    foreach ($codes as $code) {
        $equipment = Equipment::query()->where('code', $code)->firstOrFail();
        $latest = EquipmentData::query()->where('equipment_id', $equipment->id)->latest('recorded_at')->firstOrFail();
        expect($equipment->status)->toBe($latest->status);
    }
})->skip(fn () => getenv('MQTT_PROTOCOL_TESTS') !== '1', 'Set MQTT_PROTOCOL_TESTS=1 with Compose simulator running.');

it('stops the host consumer cleanly on a termination signal', function (int $signal) {
    $process = new Process([PHP_BINARY, 'artisan', 'mes:consume-mqtt', '--no-ansi'], base_path(), [
        'APP_ENV' => 'local',
        'MQTT_HOST' => config('mqtt.host'),
        'MQTT_PORT' => (string) config('mqtt.port'),
        'MQTT_FACTORY' => 'signal-'.bin2hex(random_bytes(6)),
        'MQTT_CLIENT_ID' => 'test-signal-'.bin2hex(random_bytes(6)),
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
    ]);
    $process->setTimeout(8);
    $process->start();

    try {
        expect($process->waitUntil(fn (string $type, string $output) => str_contains($output, '已订阅')))->toBeTrue();
        $process->signal($signal);
        $exitCode = $process->wait();

        expect($exitCode)->toBe(0);
        expect($process->getOutput())->toContain('已处理 0 条设备消息');
    } finally {
        if ($process->isRunning()) {
            $process->stop();
        }
    }
})->with(['SIGTERM' => SIGTERM, 'SIGINT' => SIGINT])
    ->skip(fn () => getenv('MQTT_PROTOCOL_TESTS') !== '1', 'Set MQTT_PROTOCOL_TESTS=1 with local EMQX running.');
