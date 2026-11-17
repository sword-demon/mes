<?php

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;

it('prunes only equipment samples older than the retention window', function () {
    $equipment = Equipment::factory()->create();
    $expired = EquipmentData::factory()->create([
        'equipment_id' => $equipment->id,
        'recorded_at' => now()->subDays(40),
    ]);
    $recent = EquipmentData::factory()->create([
        'equipment_id' => $equipment->id,
        'recorded_at' => now()->subDay(),
    ]);

    $this->artisan('mes:prune-equipment-data', ['--days' => 31])
        ->expectsOutputToContain('已清理 1 条')
        ->assertSuccessful();

    $this->assertDatabaseMissing('mes_equipment_data', ['id' => $expired->id]);
    $this->assertDatabaseHas('mes_equipment_data', ['id' => $recent->id]);
});
