<?php

use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\ProcessRoute;
use App\Modules\Process\Model\RouteOperation;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\ProcessSeeder;
use Database\Seeders\ProductSeeder;

test('the process seeder covers eight processes and three routes with two audited', function () {
    $this->seed(MaterialSeeder::class);
    $this->seed(ProductSeeder::class);
    $this->seed(ProcessSeeder::class);

    expect(Process::query()->count())->toBe(8)
        ->and(Process::query()->distinct()->pluck('type')->sort()->values()->all())
        ->toBe(['准备', '加工', '包装', '检验'])
        ->and(Process::query()->where('requires_inspection', true)->count())->toBe(3)
        ->and(ProcessRoute::query()->count())->toBe(3)
        ->and(ProcessRoute::query()->where('audit_status', ProcessRoute::APPROVED)->count())->toBe(2)
        ->and(ProcessRoute::query()->where('audit_status', ProcessRoute::PENDING)->count())->toBe(1)
        ->and(ProcessRoute::query()->where('audit_status', ProcessRoute::APPROVED)->whereNull('audited_at')->count())->toBe(0)
        ->and(RouteOperation::query()->count())->toBe(19);
});

test('the process seeder is idempotent and repairs a missing operation', function () {
    $this->seed(MaterialSeeder::class);
    $this->seed(ProductSeeder::class);
    $this->seed(ProcessSeeder::class);

    $auditedAt = ProcessRoute::query()->where('code', 'RT-P001-01')->firstOrFail()->audited_at;
    RouteOperation::query()->firstOrFail()->delete();

    $this->seed(ProcessSeeder::class);

    expect(Process::query()->count())->toBe(8)
        ->and(ProcessRoute::query()->count())->toBe(3)
        ->and(RouteOperation::query()->count())->toBe(19)
        ->and(ProcessRoute::query()->where('code', 'RT-P001-01')->firstOrFail()->audited_at?->timestamp)
        ->toBe($auditedAt?->timestamp);
});
