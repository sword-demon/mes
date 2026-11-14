<?php

use App\Modules\Bom\Model\Bom;
use App\Modules\Bom\Model\BomItem;
use App\Modules\Product\Model\Product;
use App\Modules\Product\Model\ProductVersion;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ProductSeeder;

test('the product seeder covers three categories, statuses, boms and is idempotent', function () {
    $this->seed(MaterialSeeder::class);
    $this->seed(ProductSeeder::class);

    $counts = [
        'products' => Product::query()->count(),
        'versions' => ProductVersion::query()->count(),
        'boms' => Bom::query()->count(),
        'items' => BomItem::query()->count(),
    ];

    $auditedAt = Bom::query()->where('audit_status', Bom::APPROVED)->orderBy('id')->firstOrFail()->audited_at;
    BomItem::query()->firstOrFail()->delete();

    $this->seed(ProductSeeder::class);

    expect($counts)->toBe(['products' => 3, 'versions' => 4, 'boms' => 4, 'items' => 12])
        ->and(Product::query()->count())->toBe($counts['products'])
        ->and(ProductVersion::query()->count())->toBe($counts['versions'])
        ->and(Bom::query()->count())->toBe($counts['boms'])
        ->and(BomItem::query()->count())->toBe($counts['items'])
        ->and(Bom::query()->where('audit_status', Bom::APPROVED)->orderBy('id')->firstOrFail()->audited_at?->timestamp)
        ->toBe($auditedAt?->timestamp);

    expect(Product::query()->distinct()->pluck('category')->sort()->values()->all())
        ->toBe(['半成品', '成品', '部件'])
        ->and(Product::query()->where('status', false)->count())->toBe(1)
        ->and(Product::query()->where('code', 'P001')->firstOrFail()->versions()->count())->toBe(2);

    $meters = Product::query()->where('code', 'P001')->firstOrFail();

    expect(Bom::query()->whereHas('productVersion', fn ($query) => $query->where('product_id', $meters->id))->count())
        ->toBe(2)
        ->and(Bom::query()->where('audit_status', Bom::APPROVED)->count())->toBe(2)
        ->and(Bom::query()->where('audit_status', Bom::DRAFT)->count())->toBe(2)
        ->and(Bom::query()->with('productVersion')->get()->pluck('productVersion.product_id')->unique()->count())->toBe(3)
        ->and(Bom::query()->distinct()->count('product_version_id'))->toBe(4)
        ->and(Bom::query()->where('audit_status', Bom::APPROVED)->whereNull('audited_at')->count())->toBe(0);
});
