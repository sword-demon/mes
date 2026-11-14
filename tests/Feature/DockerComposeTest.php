<?php

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function compose(): array
{
    return Yaml::parseFile(base_path('docker/docker-compose.yml'));
}

/**
 * @return array<string, string>
 */
function publishedPorts(string $service): array
{
    $ports = [];

    foreach (compose()['services'][$service]['ports'] as $mapping) {
        $separator = strrpos($mapping, ':');
        $container = substr($mapping, $separator + 1);
        $host = substr($mapping, 0, $separator);

        if (preg_match('/\$\{[A-Z_]+:-(\d+)\}/', $host, $matches)) {
            $host = $matches[1];
        }

        $ports[$container] = $host;
    }

    return $ports;
}

test('middleware publishes only high host ports', function () {
    expect(publishedPorts('mysql'))->toBe(['3306' => '13307'])
        ->and(publishedPorts('redis'))->toBe(['6379' => '16379'])
        ->and(publishedPorts('emqx'))->toBe([
            '1883' => '11883',
            '8883' => '11888',
            '18083' => '18083',
        ]);
});

test('mysql persists its data directory to a named volume', function () {
    expect(compose()['services']['mysql']['volumes'])->toContain('mysql-data:/var/lib/mysql')
        ->and(compose()['volumes'])->toHaveKey('mysql-data');
});

test('the simulator waits for a healthy broker and reaches it by service name', function () {
    $simulator = compose()['services']['simulator'];

    expect($simulator['depends_on']['emqx']['condition'])->toBe('service_healthy')
        ->and($simulator['environment']['MQTT_HOST'])->toBe('emqx')
        ->and($simulator['environment']['MQTT_PORT'])->toBe(1883);
});

test('the host env template targets the compose ports', function () {
    $env = parse_ini_string(file_get_contents(base_path('.env.docker.example')));

    expect($env['DB_HOST'])->toBe('127.0.0.1')
        ->and($env['DB_PORT'])->toBe('13307')
        ->and($env['REDIS_PORT'])->toBe('16379')
        ->and($env['MQTT_PORT'])->toBe('11883')
        ->and($env['QUEUE_CONNECTION'])->toBe('redis');
});
