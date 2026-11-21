<?php

use App\Console\Commands\PruneEquipmentData;
use App\Console\Commands\ScanInventoryAlerts;
use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

DevCommands::register('cd frontend/web && npm run dev', 'web');

// 覆盖框架默认注册的 `queue:listen --tries=1 --timeout=0`：默认监听不重试、
// 不超时，也无法区分业务队列与 AI 队列。
DevCommands::register('php artisan queue:work redis --queue=default --timeout=60 --tries=3 --max-time=3600', 'queue');
DevCommands::register('php artisan queue:work redis --queue=ai --timeout=180 --tries=2 --max-time=3600', 'ai-worker');
DevCommands::register('php artisan schedule:work', 'scheduler');
DevCommands::register('php artisan mes:consume-mqtt', 'mqtt');

Schedule::command(ScanInventoryAlerts::class)
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command(PruneEquipmentData::class)
    ->dailyAt('03:10')
    ->withoutOverlapping(30);
