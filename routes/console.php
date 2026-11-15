<?php

use App\Console\Commands\ScanInventoryAlerts;
use Illuminate\Foundation\DevCommands;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

DevCommands::register('cd frontend/web && npm run dev', 'web');

Schedule::command(ScanInventoryAlerts::class)
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
