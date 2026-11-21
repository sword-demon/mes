<?php

use App\Modules\Inventory\Model\Warehouse;
use App\Modules\Organization\Model\Factory;
use App\Modules\System\Model\User;
use Illuminate\Support\Facades\DB;

it('backfills unconfigured users, line users and warehouses to the first enabled factory', function () {
    Factory::factory()->create(['code' => 'DISABLED', 'status' => false]);
    [$factory, $firstLine, $secondLine] = factoryWithLines();

    $user = User::factory()->create(['factory_id' => null]);
    $lineUser = userWithPermissions(['data.scope.line'], 'workshop_director');
    $lineUser->update(['factory_id' => null]);
    $warehouse = Warehouse::factory()->create(['factory_id' => null]);

    $this->artisan('mes:data-scope:backfill')->assertSuccessful();

    expect($user->fresh()->factory_id)->toBe($factory->id)
        ->and($lineUser->fresh()->factory_id)->toBe($factory->id)
        ->and($warehouse->fresh()->factory_id)->toBe($factory->id)
        ->and($lineUser->productionLines()->orderBy('mes_production_lines.id')->pluck('mes_production_lines.id')->all())
        ->toBe([$firstLine->id, $secondLine->id]);
});

it('keeps the backfill idempotent', function () {
    [$factory] = factoryWithLines();
    $lineUser = userWithPermissions(['data.scope.line'], 'workshop_director');
    $lineUser->update(['factory_id' => $factory->id]);

    $this->artisan('mes:data-scope:backfill')->assertSuccessful();
    $this->artisan('mes:data-scope:backfill')->assertSuccessful();

    expect(DB::table('sys_user_production_lines')->where('user_id', $lineUser->id)->count())->toBe(2);
});

it('does not re-add production lines that were narrowed manually', function () {
    [$factory, $firstLine, $secondLine] = factoryWithLines();
    $lineUser = userWithPermissions(['data.scope.line'], 'workshop_director');
    $lineUser->update(['factory_id' => $factory->id]);

    $this->artisan('mes:data-scope:backfill')->assertSuccessful();

    $lineUser->productionLines()->detach($secondLine->id);

    $this->artisan('mes:data-scope:backfill')->assertSuccessful();

    expect($lineUser->productionLines()->pluck('mes_production_lines.id')->all())->toBe([$firstLine->id]);
});

it('leaves already configured users and warehouses untouched', function () {
    factoryWithLines();
    $otherFactory = Factory::factory()->create();
    $user = User::factory()->create(['factory_id' => $otherFactory->id]);
    $warehouse = Warehouse::factory()->create(['factory_id' => $otherFactory->id]);

    $this->artisan('mes:data-scope:backfill')->assertSuccessful();

    expect($user->fresh()->factory_id)->toBe($otherFactory->id)
        ->and($warehouse->fresh()->factory_id)->toBe($otherFactory->id);
});

it('fails the backfill when no enabled factory exists', function () {
    Factory::factory()->create(['status' => false]);
    $user = User::factory()->create(['factory_id' => null]);

    $this->artisan('mes:data-scope:backfill')
        ->expectsOutputToContain('未找到启用工厂')
        ->assertFailed();

    expect($user->fresh()->factory_id)->toBeNull();
});

it('reports unconfigured users, line users and warehouses', function () {
    [$factory] = factoryWithLines();
    $missingUser = User::factory()->create(['email' => 'missing-scope@mes.test', 'factory_id' => null]);
    $lineUser = userWithPermissions(['data.scope.line'], 'workshop_director');
    $lineUser->update(['factory_id' => $factory->id]);
    $missingWarehouse = Warehouse::factory()->create(['code' => 'WH-MISSING', 'factory_id' => null]);
    $configuredUser = User::factory()->create(['email' => 'configured@mes.test', 'factory_id' => $factory->id]);
    $configuredWarehouse = Warehouse::factory()->create(['code' => 'WH-CONFIGURED', 'factory_id' => $factory->id]);

    $this->artisan('mes:data-scope:report')
        ->expectsOutputToContain($missingUser->email)
        ->expectsOutputToContain($lineUser->email)
        ->expectsOutputToContain($missingWarehouse->code)
        ->doesntExpectOutputToContain($configuredUser->email)
        ->doesntExpectOutputToContain($configuredWarehouse->code)
        ->assertSuccessful();
});

it('reports a complete data scope configuration', function () {
    [$factory] = factoryWithLines();
    User::factory()->create(['factory_id' => $factory->id]);
    Warehouse::factory()->create(['factory_id' => $factory->id]);

    $this->artisan('mes:data-scope:report')
        ->expectsOutputToContain('数据范围配置完整')
        ->assertSuccessful();
});

it('fails the report when no enabled factory exists', function () {
    Factory::factory()->create(['status' => false]);

    $this->artisan('mes:data-scope:report')
        ->expectsOutputToContain('未找到启用工厂')
        ->assertFailed();
});
