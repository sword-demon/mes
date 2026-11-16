<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use Database\Seeders\EquipmentSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\SystemSeeder;

test('the equipment seeder seeds five devices and twenty alarms covering every status and is idempotent', function () {
    $this->seed(SystemSeeder::class);
    $this->seed(OrganizationSeeder::class);
    $this->seed(EquipmentSeeder::class);

    expect(Equipment::query()->count())->toBe(5)
        ->and(Equipment::query()->distinct()->pluck('status')->sort()->values()->all())
        ->toBe(['fault', 'idle', 'offline', 'repair', 'running'])
        ->and(Equipment::query()->distinct()->pluck('type')->sort()->values()->all())
        ->toBe(['CNC 加工中心', 'SMT 贴片机', '功能测试设备', '回流焊设备', '自动组装设备'])
        ->and(Equipment::query()->whereNotNull('owner_id')->count())->toBe(5)
        ->and(Equipment::query()->whereNotNull('production_line_id')->count())->toBe(5);

    $this->seed(EquipmentSeeder::class);

    expect(Equipment::query()->count())->toBe(5);

    expect(EquipmentAlarm::query()->count())->toBe(20)
        ->and(EquipmentAlarm::query()->distinct()->pluck('level')->sort()->values()->all())
        ->toBe(['critical', 'info', 'warning'])
        ->and(EquipmentAlarm::query()->distinct()->pluck('status')->sort()->values()->all())
        ->toBe(['closed', 'confirmed', 'pending', 'processing'])
        ->and(EquipmentAlarm::query()->whereNotNull('threshold')->count())->toBe(20)
        ->and(EquipmentAlarm::query()->whereNotNull('measured_value')->count())->toBe(20)
        ->and(EquipmentAlarm::query()->whereNotNull('triggered_at')->count())->toBe(20)
        ->and(EquipmentAlarm::query()->where('status', EquipmentAlarm::PROCESSING)->whereNull('handling_note')->count())->toBe(0)
        ->and(EquipmentAlarm::query()->where('status', EquipmentAlarm::CLOSED)->whereNull('handling_note')->count())->toBe(0);

    $this->seed(EquipmentSeeder::class);

    expect(Equipment::query()->count())->toBe(5)
        ->and(EquipmentAlarm::query()->count())->toBe(20);
});
