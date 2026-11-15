<?php

use App\Modules\Quality\Model\DefectiveProduct;
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

it('seeds defects in every processing status with the full demo seed', function () {
    $this->seed();
    $this->seed();

    expect(DefectiveProduct::query()->distinct()->orderBy('status')->pluck('status')->all())
        ->toBe([
            DefectiveProduct::CLOSED,
            DefectiveProduct::PENDING,
            DefectiveProduct::PENDING_RECHECK,
            DefectiveProduct::PROCESSING,
        ])
        ->and(DefectiveProduct::query()->count())->toBe(5);
});
