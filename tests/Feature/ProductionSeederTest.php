<?php

use App\Modules\Production\Model\ProductionDispatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\System\Model\User;
use Database\Seeders\MaterialSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\ProcessSeeder;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\SystemSeeder;

function seedProductionDemo(): void
{
    test()->seed([
        SystemSeeder::class,
        OrganizationSeeder::class,
        MaterialSeeder::class,
        ProductSeeder::class,
        ProcessSeeder::class,
        ProductionSeeder::class,
    ]);
}

test('the production seeder matches every dispatch to its task and is idempotent', function () {
    seedProductionDemo();

    $counts = [
        'orders' => ProductionOrder::query()->count(),
        'tasks' => ProductionTask::query()->count(),
        'dispatches' => ProductionDispatch::query()->count(),
    ];

    expect($counts)->toBe(['orders' => 20, 'tasks' => 30, 'dispatches' => 60]);

    ProductionDispatch::query()->with(['productionTask', 'routeOperation', 'employee', 'team'])->each(
        function (ProductionDispatch $dispatch): void {
            expect($dispatch->productionTask->production_order_id)->not->toBeNull()
                ->and($dispatch->routeOperation->route_id)->toBe($dispatch->productionTask->route_id)
                ->and($dispatch->employee->team_id)->toBe($dispatch->team_id)
                ->and($dispatch->employee->status)->toBeTrue()
                ->and($dispatch->team->status)->toBeTrue();
        },
    );

    $this->seed(ProductionSeeder::class);

    expect(ProductionOrder::query()->count())->toBe($counts['orders'])
        ->and(ProductionTask::query()->count())->toBe($counts['tasks'])
        ->and(ProductionDispatch::query()->count())->toBe($counts['dispatches']);
});

test('the seeded operator can see the dispatches assigned to their employee record', function () {
    seedProductionDemo();

    $operator = User::query()->where('email', 'operator3@mes.test')->firstOrFail();

    expect($operator->employee_id)->not->toBeNull();

    bearer($operator)->getJson('/api/v1/production/dispatches/mine?per_page=100')
        ->assertOk()
        ->assertJsonCount(21, 'data.items')
        ->assertJsonPath('data.items.0.employee.id', $operator->employee_id);
});

test('the seeded workshop director can load the dispatch form options', function () {
    seedProductionDemo();

    $director = User::query()->where('email', 'workshop@mes.test')->firstOrFail();

    bearer($director)->getJson('/api/v1/basic-data/teams?status=1')
        ->assertOk();
    bearer($director)->getJson('/api/v1/basic-data/employees?status=1')
        ->assertOk();
    bearer($director)->getJson('/api/v1/production/tasks?per_page=100')
        ->assertOk();
    bearer($director)->getJson('/api/v1/production/dispatches')
        ->assertOk();
});
