<?php

use App\Modules\Material\Model\Material;
use Database\Seeders\MaterialSeeder;

test('the material seeder seeds ten materials covering every type and is idempotent', function () {
    $this->seed(MaterialSeeder::class);

    $count = Material::query()->count();

    $this->seed(MaterialSeeder::class);

    expect($count)->toBe(10)
        ->and(Material::query()->count())->toBe($count)
        ->and(Material::query()->distinct()->pluck('type')->sort()->values()->all())
        ->toBe(['包装材料', '半成品', '原材料', '成品', '辅助材料'])
        ->and(Material::query()->where('batch_managed', false)->count())->toBeGreaterThan(0)
        ->and(Material::query()->where('min_stock', '>', 0)->count())->toBe(10)
        ->and(Material::query()->where('safety_stock', '>', 0)->count())->toBe(10);
});
