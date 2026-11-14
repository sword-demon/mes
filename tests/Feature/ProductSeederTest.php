<?php

use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use Database\Seeders\ProductSeeder;

test('the product seeder covers three categories and statuses and is idempotent', function () {
    $this->seed(ProductSeeder::class);

    $counts = [
        'products' => Product::query()->count(),
        'versions' => ProductVersion::query()->count(),
    ];

    $this->seed(ProductSeeder::class);

    expect($counts)->toBe(['products' => 3, 'versions' => 4])
        ->and(Product::query()->count())->toBe($counts['products'])
        ->and(ProductVersion::query()->count())->toBe($counts['versions']);

    expect(Product::query()->distinct()->pluck('category')->sort()->values()->all())
        ->toBe(['半成品', '成品', '部件'])
        ->and(Product::query()->where('status', false)->count())->toBe(1)
        ->and(Product::query()->where('code', 'P001')->firstOrFail()->versions()->count())->toBe(2);
});
