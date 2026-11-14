<?php

test('compose file orchestrates middleware on high host ports', function () {
    $compose = file_get_contents(base_path('docker/docker-compose.yml'));

    expect($compose)
        ->toContain('mysql:')
        ->toContain('redis:')
        ->toContain('emqx:')
        ->toContain('simulator:')
        ->toContain('${MYSQL_HOST_PORT:-13307}:3306')
        ->toContain('${REDIS_HOST_PORT:-16379}:6379')
        ->toContain('${MQTT_HOST_PORT:-11883}:1883')
        ->toContain('${MQTT_WS_HOST_PORT:-11888}:8883')
        ->toContain('${EMQX_DASHBOARD_HOST_PORT:-18083}:18083')
        ->toContain('mysql-data:/var/lib/mysql')
        ->toContain('condition: service_healthy');
});

test('host env template matches the compose contract', function () {
    $env = file_get_contents(base_path('.env.docker.example'));

    expect($env)
        ->toContain('DB_PORT=13307')
        ->toContain('REDIS_PORT=16379')
        ->toContain('MQTT_PORT=11883')
        ->toContain('QUEUE_CONNECTION=redis')
        ->toContain('DB_PORT=13306');
});

test('simulator only grows output while a device is running', function () {
    $source = file_get_contents(base_path('docker/simulator/publish.py'));

    expect($source)
        ->toContain('PRODUCTIVE_STATES = {RUNNING}')
        ->toContain('if running:')
        ->toContain('output[device] += random.randint(1, 5)');
});
