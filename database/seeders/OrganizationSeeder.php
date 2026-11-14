<?php

namespace Database\Seeders;

use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Team;
use App\Modules\Organization\Model\Workshop;
use App\Modules\System\Model\Department;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * The demo org chart from PRD 10.1: one factory, two workshops, three lines.
     *
     * Every node carries an explicit code so re-running updates rows in place
     * instead of inserting duplicates.
     */
    private const WORKSHOPS = [
        ['code' => 'WS01', 'name' => '一号车间', 'manager' => '王建国', 'lines' => [
            ['code' => 'PL01', 'name' => 'SMT 一线', 'capacity' => 120, 'manager' => '刘志强'],
            ['code' => 'PL02', 'name' => 'SMT 二线', 'capacity' => 120, 'manager' => '陈海'],
        ], 'teams' => [
            ['code' => 'TEAM01', 'name' => 'A班', 'leader' => '刘志强'],
            ['code' => 'TEAM02', 'name' => 'B班', 'leader' => '陈海'],
        ]],
        ['code' => 'WS02', 'name' => '二号车间', 'manager' => '赵敏', 'lines' => [
            ['code' => 'PL03', 'name' => '总装线', 'capacity' => 90, 'manager' => '孙丽'],
        ], 'teams' => [
            ['code' => 'TEAM03', 'name' => 'A班', 'leader' => '孙丽'],
        ]],
    ];

    /**
     * Ten employees spread over both workshops; the department is resolved by
     * code so a re-run of SystemSeeder first keeps the link intact.
     */
    private const EMPLOYEES = [
        ['EMP001', '王建国', 'workshop-a', 'TEAM01', '车间主任', '高级'],
        ['EMP002', '刘志强', 'workshop-a', 'TEAM01', '产线班长', '高级'],
        ['EMP003', '陈海', 'workshop-a', 'TEAM02', '产线班长', '高级'],
        ['EMP004', '李秀兰', 'workshop-a', 'TEAM01', '操作工', '中级'],
        ['EMP005', '张伟', 'workshop-a', 'TEAM02', '操作工', '中级'],
        ['EMP006', '周涛', 'workshop-a', 'TEAM02', '操作工', '初级'],
        ['EMP007', '赵敏', 'workshop-b', 'TEAM03', '车间主任', '高级'],
        ['EMP008', '孙丽', 'workshop-b', 'TEAM03', '产线班长', '中级'],
        ['EMP009', '吴敏', 'workshop-b', 'TEAM03', '操作工', '中级'],
        ['EMP010', '郑凯', 'workshop-b', 'TEAM03', '操作工', '初级'],
    ];

    public function run(): void
    {
        $factory = Factory::query()->updateOrCreate(
            ['code' => 'FAC01'],
            [
                'name' => '演示工厂',
                'address' => '苏州市工业园区星湖街 328 号',
                'contact' => '王建国',
                'phone' => '0512-88880000',
                'status' => true,
            ],
        );

        $teamIds = [];

        foreach (self::WORKSHOPS as $index => $definition) {
            $workshop = Workshop::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'factory_id' => $factory->id,
                    'name' => $definition['name'],
                    'manager' => $definition['manager'],
                    'status' => true,
                ],
            );

            foreach ($definition['lines'] as $line) {
                ProductionLine::query()->updateOrCreate(
                    ['code' => $line['code']],
                    [
                        'workshop_id' => $workshop->id,
                        'name' => $line['name'],
                        'capacity' => $line['capacity'],
                        'manager' => $line['manager'],
                        'status' => true,
                    ],
                );
            }

            foreach ($definition['teams'] as $team) {
                $teamIds[$team['code']] = Team::query()->updateOrCreate(
                    ['code' => $team['code']],
                    [
                        'workshop_id' => $workshop->id,
                        'name' => $team['name'],
                        'leader' => $team['leader'],
                        'status' => true,
                    ],
                )->id;
            }
        }

        $departmentIds = Department::query()->pluck('id', 'code');

        foreach (self::EMPLOYEES as $index => [$number, $name, $department, $team, $position, $skill]) {
            Employee::query()->updateOrCreate(
                ['employee_no' => $number],
                [
                    'name' => $name,
                    'department_id' => $departmentIds[$department] ?? null,
                    'team_id' => $teamIds[$team],
                    'position' => $position,
                    'skill_level' => $skill,
                    'phone' => '139'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                    'status' => true,
                ],
            );
        }
    }
}
