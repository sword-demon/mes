<?php

use Database\Seeders\QualitySeeder;

it('seeds the PRD standards and classified defects without duplicates', function () {
    $this->seed(QualitySeeder::class);
    $this->seed(QualitySeeder::class);
    $actor = userWithPermissions(['quality.standards.index', 'quality.defect-codes.index']);

    bearer($actor)->getJson('/api/v1/quality/inspection-items')
        ->assertOk()->assertJsonCount(3, 'data.items')
        ->assertJsonPath('data.items.0.name', '工作电压')
        ->assertJsonPath('data.items.0.standard_value', '5.000')
        ->assertJsonPath('data.items.1.min_value', '0.000')
        ->assertJsonPath('data.items.2.max_value', '101.000');

    bearer($actor)->getJson('/api/v1/quality/defect-codes')
        ->assertOk()->assertJsonCount(3, 'data.items')
        ->assertJsonPath('data.items.0.category', '外观');
});
