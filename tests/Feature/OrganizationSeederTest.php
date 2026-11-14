<?php

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Model\Workshop;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\SystemSeeder;

test('the organization seeder matches the PRD demo org chart and is idempotent', function () {
    $this->seed(SystemSeeder::class);
    $this->seed(OrganizationSeeder::class);

    $counts = [
        'factories' => Factory::query()->count(),
        'workshops' => Workshop::query()->count(),
        'lines' => ProductionLine::query()->count(),
        'teams' => Team::query()->count(),
        'employees' => Employee::query()->count(),
    ];

    $this->seed(OrganizationSeeder::class);

    expect($counts)->toBe([
        'factories' => 1,
        'workshops' => 2,
        'lines' => 3,
        'teams' => 3,
        'employees' => 10,
    ])
        ->and(Factory::query()->count())->toBe($counts['factories'])
        ->and(Workshop::query()->count())->toBe($counts['workshops'])
        ->and(ProductionLine::query()->count())->toBe($counts['lines'])
        ->and(Team::query()->count())->toBe($counts['teams'])
        ->and(Employee::query()->count())->toBe($counts['employees']);

    $factory = Factory::query()->where('code', 'FAC01')->firstOrFail();

    expect($factory->workshops()->count())->toBe(2)
        ->and($factory->workshops()->withCount('productionLines')->get()->sum('production_lines_count'))->toBe(3)
        ->and(Employee::query()->whereNotNull('department_id')->count())->toBe(10)
        ->and(Employee::query()->whereNotNull('team_id')->count())->toBe(10);
});

test('the seeded employees sit in the departments the system seeder created', function () {
    $this->seed(SystemSeeder::class);
    $this->seed(OrganizationSeeder::class);

    $workshopDirector = Employee::query()->where('employee_no', 'EMP001')->firstOrFail();

    expect($workshopDirector->department->code)->toBe('workshop-a')
        ->and($workshopDirector->team->code)->toBe('TEAM01')
        ->and($workshopDirector->skill_level)->toBe('高级');
});
