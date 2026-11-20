<?php

use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;
use App\Modules\System\Model\User;

/**
 * @return array{0: Factory, 1: ProductionLine, 2: ProductionLine}
 */
function factoryWithLines(): array
{
    $factory = Factory::factory()->create();
    $workshop = Workshop::factory()->create(['factory_id' => $factory->id]);

    return [
        $factory,
        ProductionLine::factory()->create(['workshop_id' => $workshop->id]),
        ProductionLine::factory()->create(['workshop_id' => $workshop->id]),
    ];
}

it('returns the line scope with the bound production lines', function () {
    [$factory, $first, $second] = factoryWithLines();
    $user = userWithPermissions(['data.scope.line'], 'workshop_director');
    $user->update(['factory_id' => $factory->id]);
    $user->productionLines()->sync([$second->id, $first->id]);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'line')
        ->assertJsonPath('data.data_scope_detail.level', 'line')
        ->assertJsonPath('data.data_scope_detail.factory_id', $factory->id)
        ->assertJsonPath('data.data_scope_detail.production_line_ids', [$first->id, $second->id]);
});

it('returns the factory scope without production lines', function () {
    [$factory] = factoryWithLines();
    $user = userWithPermissions(['data.scope.factory'], 'factory_admin');
    $user->update(['factory_id' => $factory->id]);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'factory')
        ->assertJsonPath('data.data_scope_detail.level', 'factory')
        ->assertJsonPath('data.data_scope_detail.factory_id', $factory->id)
        ->assertJsonPath('data.data_scope_detail.production_line_ids', []);
});

it('returns the all scope for a super admin', function () {
    bearer(superAdmin())->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'all')
        ->assertJsonPath('data.data_scope_detail.level', 'all')
        ->assertJsonPath('data.data_scope_detail.factory_id', null)
        ->assertJsonPath('data.data_scope_detail.production_line_ids', []);
});

it('returns the all scope for a non-super-admin holding the all data permission', function () {
    $user = userWithPermissions(['data.scope.all'], 'auditor');

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'all')
        ->assertJsonPath('data.data_scope_detail.level', 'all');
});

it('keeps the granted scope but resolves none when a factory-scoped user has no factory', function () {
    $user = userWithPermissions(['data.scope.factory'], 'factory_admin');

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'factory')
        ->assertJsonPath('data.data_scope_detail.level', 'none');
});

it('keeps the granted scope but resolves none when a line-scoped user has no production line', function () {
    [$factory] = factoryWithLines();
    $user = userWithPermissions(['data.scope.line'], 'workshop_director');
    $user->update(['factory_id' => $factory->id]);

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'line')
        ->assertJsonPath('data.data_scope_detail.level', 'none');
});

it('returns none when a user holds no data scope permission', function () {
    $user = userWithPermissions(['production.orders.index'], 'operator_without_scope');

    bearer($user)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.data_scope', 'none')
        ->assertJsonPath('data.data_scope_detail.level', 'none');
});

it('stores the factory and production lines through the user admin api', function () {
    [$factory, $first, $second] = factoryWithLines();

    $userId = bearer(superAdmin())->postJson('/api/v1/system/users', [
        'name' => '张三',
        'email' => 'zhang@mes.test',
        'password' => 'password-123',
        'factory_id' => $factory->id,
        'production_line_ids' => [$first->id, $second->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.factory_id', $factory->id)
        ->assertJsonCount(2, 'data.production_lines')
        ->json('data.id');

    $user = User::query()->findOrFail($userId);

    expect($user->productionLines()->orderBy('mes_production_lines.id')->pluck('mes_production_lines.id')->all())
        ->toBe([$first->id, $second->id]);
});

it('replaces the production lines when the user is updated', function () {
    [$factory, $first, $second] = factoryWithLines();
    $user = User::factory()->create(['factory_id' => $factory->id]);
    $user->productionLines()->sync([$first->id]);

    bearer(superAdmin())->putJson("/api/v1/system/users/{$user->id}", [
        'production_line_ids' => [$second->id],
    ])
        ->assertOk()
        ->assertJsonCount(1, 'data.production_lines')
        ->assertJsonPath('data.production_lines.0.id', $second->id);

    expect($user->fresh()->productionLines()->pluck('mes_production_lines.id')->all())->toBe([$second->id]);
});
