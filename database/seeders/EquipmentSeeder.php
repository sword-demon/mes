<?php

namespace Database\Seeders;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;
use Illuminate\Database\Seeder;
use RuntimeException;

class EquipmentSeeder extends Seeder
{
    /**
     * Five demo devices covering every status and device type.
     */
    private const EQUIPMENTS = [
        ['code' => 'EQ-SMT-01', 'name' => 'SMT 贴片机一号', 'type' => 'SMT 贴片机', 'workshop' => 'WS01', 'line' => 'PL01', 'model' => 'SMT-X200', 'status' => Equipment::RUNNING, 'owner' => 'EMP002'],
        ['code' => 'EQ-REFLOW-01', 'name' => '回流焊设备一号', 'type' => '回流焊设备', 'workshop' => 'WS01', 'line' => 'PL01', 'model' => 'RF-800', 'status' => Equipment::IDLE, 'owner' => 'EMP004'],
        ['code' => 'EQ-CNC-01', 'name' => 'CNC 加工中心一号', 'type' => 'CNC 加工中心', 'workshop' => 'WS01', 'line' => 'PL02', 'model' => 'CNC-850', 'status' => Equipment::FAULT, 'owner' => 'EMP003'],
        ['code' => 'EQ-ASM-01', 'name' => '自动组装设备一号', 'type' => '自动组装设备', 'workshop' => 'WS02', 'line' => 'PL03', 'model' => 'ASM-300', 'status' => Equipment::REPAIR, 'owner' => 'EMP008'],
        ['code' => 'EQ-TEST-01', 'name' => '功能测试设备一号', 'type' => '功能测试设备', 'workshop' => 'WS02', 'line' => 'PL03', 'model' => 'FT-100', 'status' => Equipment::OFFLINE, 'owner' => 'EMP009'],
    ];

    public function run(): void
    {
        $workshops = Workshop::query()->whereIn('code', ['WS01', 'WS02'])->get()->keyBy('code');
        $lines = ProductionLine::query()->whereIn('code', ['PL01', 'PL02', 'PL03'])->get()->keyBy('code');
        $employees = Employee::query()
            ->whereIn('employee_no', ['EMP002', 'EMP003', 'EMP004', 'EMP008', 'EMP009'])
            ->get()
            ->keyBy('employee_no');

        foreach (self::EQUIPMENTS as $definition) {
            $workshop = $workshops->get($definition['workshop']);
            $line = $lines->get($definition['line']);
            $owner = $employees->get($definition['owner']);

            if ($workshop === null || $line === null || $owner === null || $line->workshop_id !== $workshop->id) {
                throw new RuntimeException('请先执行组织架构 Seeder 再执行 EquipmentSeeder');
            }

            Equipment::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'workshop_id' => $workshop->id,
                    'production_line_id' => $line->id,
                    'model' => $definition['model'],
                    'status' => $definition['status'],
                    'owner_id' => $owner->id,
                ],
            );
        }
    }
}
