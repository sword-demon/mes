<?php

use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use App\Modules\System\Service\AccessControlService;
use Database\Seeders\SystemSeeder;

it('grants the warehouse manager access to the inventory alert page and actions', function () {
    $this->seed(SystemSeeder::class);
    $managerRole = Role::query()->where('code', 'warehouse_manager')->firstOrFail();
    $manager = User::factory()->create();
    $manager->roles()->attach($managerRole);

    $access = app(AccessControlService::class);

    expect($access->allows($manager, 'page.material.inventory-alerts'))->toBeTrue()
        ->and($access->allows($manager, 'inventory.alerts.index'))->toBeTrue()
        ->and($access->allows($manager, 'inventory.alerts.confirm'))->toBeTrue()
        ->and($access->allows($manager, 'inventory.alerts.close'))->toBeTrue()
        ->and($access->allows($manager, 'material.requisitions.index'))->toBeTrue()
        ->and($access->allows($manager, 'material.requisitions.issue'))->toBeTrue()
        ->and($access->allows($manager, 'material.requisitions.create'))->toBeFalse();

    $operator = User::factory()->create();
    $operator->roles()->attach(Role::query()->where('code', 'production_operator')->firstOrFail());

    expect($access->allows($operator, 'material.requisitions.create'))->toBeTrue()
        ->and($access->allows($operator, 'material.requisitions.issue'))->toBeFalse();
});
