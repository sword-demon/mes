<?php

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function productionCompose(): array
{
    return Yaml::parseFile(base_path('docker/docker-compose.prod.yml'));
}

/**
 * @param  array<string, string|false>  $env
 */
function productionComposeProcess(array $env = []): Process
{
    return new Process([
        'docker', 'compose', '--env-file', '/dev/null',
        '-f', base_path('docker/docker-compose.prod.yml'), 'config', '--format', 'json',
    ], base_path(), [
        'DB_PASSWORD' => 'prod-db-secret',
        'MYSQL_ROOT_PASSWORD' => 'prod-root-secret',
        'EMQX_DASHBOARD_PASSWORD' => 'prod-emqx-secret',
        ...$env,
    ]);
}

/**
 * @param  array<string, string|false>  $env
 * @return array<string, mixed>
 */
function productionComposeConfig(array $env = []): array
{
    static $cache = [];
    $key = json_encode($env);

    if (! isset($cache[$key])) {
        $process = productionComposeProcess($env);
        $process->mustRun();
        $cache[$key] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    return $cache[$key];
}

test('production compose orchestrates only middleware', function () {
    expect(array_keys(productionCompose()['services']))->toEqualCanonicalizing(['mysql', 'redis', 'emqx']);
});

test('production compose persists middleware state in named volumes', function () {
    $compose = productionCompose();

    expect($compose['services']['mysql']['volumes'])->toContain('mysql-data:/var/lib/mysql')
        ->and($compose['services']['redis']['volumes'])->toContain('redis-data:/data')
        ->and($compose['services']['emqx']['volumes'])->toContain('emqx-data:/opt/emqx/data')
        ->and($compose['volumes'])->toHaveKeys(['mysql-data', 'redis-data', 'emqx-data', 'emqx-log']);
});

test('production compose binds middleware ports to loopback by default', function () {
    $compose = productionCompose();

    expect($compose['services']['mysql']['ports'])->toEqualCanonicalizing(['127.0.0.1:${DB_PORT:-13307}:3306'])
        ->and($compose['services']['redis']['ports'])->toEqualCanonicalizing(['127.0.0.1:${REDIS_PORT:-16379}:6379'])
        ->and($compose['services']['emqx']['ports'])->toEqualCanonicalizing([
            '${MQTT_BIND:-127.0.0.1}:${MQTT_PORT:-11883}:1883',
            '127.0.0.1:${EMQX_DASHBOARD_HOST_PORT:-18083}:18083',
        ]);
});

test('production compose mounts only the mysql config from the host', function () {
    $bindSources = collect(productionComposeConfig()['services'])
        ->flatMap(fn (array $service) => collect($service['volumes'] ?? [])->where('type', 'bind')->pluck('source'))
        ->unique()
        ->values()
        ->all();

    expect($bindSources)->toBe([base_path('docker/mysql/my.cnf')]);
});

test('production compose resolves with required secrets', function () {
    $config = productionComposeConfig();

    expect($config['name'])->toBe('mes-prod')
        ->and($config['services']['mysql']['environment']['MYSQL_PASSWORD'])->toBe('prod-db-secret')
        ->and($config['services']['emqx']['environment']['EMQX_DASHBOARD__DEFAULT_PASSWORD'])->toBe('prod-emqx-secret');
});

test('production compose refuses to start without required secrets', function () {
    $process = productionComposeProcess([
        'DB_PASSWORD' => false,
        'MYSQL_ROOT_PASSWORD' => false,
        'EMQX_DASHBOARD_PASSWORD' => false,
    ]);
    $process->run();

    expect($process->isSuccessful())->toBeFalse()
        ->and($process->getErrorOutput().$process->getOutput())->toContain('DB_PASSWORD');
});

test('the production nginx template serves the SPA and forwards the API to php-fpm', function () {
    $nginx = file_get_contents(base_path('docker/nginx/mes.conf.example'));

    expect($nginx)->toContain('root /var/www/mes/frontend/web/dist;')
        ->toContain('try_files $uri $uri/ /index.html;')
        ->toContain('location = /api')
        ->toContain('location /api/')
        ->toContain('location ^~ /api-docs')
        ->toContain('fastcgi_pass unix:/run/php/php8.4-fpm.sock;')
        ->toContain('fastcgi_param SCRIPT_FILENAME /var/www/mes/public/index.php;')
        ->toContain('alias /var/www/mes/storage/app/public/;');
});
