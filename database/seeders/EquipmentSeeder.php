<?php

namespace Database\Seeders;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentAlarm;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Organization\Model\Employee;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;
use App\Modules\System\Model\User;
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

    /**
     * Twenty deterministic alarms covering every level and status.
     */
    private const ALARMS = [
        ['code' => 'ALM-DEMO-001', 'equipment' => 'EQ-SMT-01', 'metric' => 'temperature', 'level' => EquipmentAlarm::LEVEL_CRITICAL, 'status' => EquipmentAlarm::CLOSED, 'measured' => 69.2, 'hours_ago' => 26, 'note' => '已停机更换温控模块，复测温度恢复正常。'],
        ['code' => 'ALM-DEMO-002', 'equipment' => 'EQ-REFLOW-01', 'metric' => 'temperature', 'level' => EquipmentAlarm::LEVEL_WARNING, 'status' => EquipmentAlarm::CLOSED, 'measured' => 65.5, 'hours_ago' => 34, 'note' => '清理风道后温度回落至阈值内。'],
        ['code' => 'ALM-DEMO-003', 'equipment' => 'EQ-CNC-01', 'metric' => 'speed', 'level' => EquipmentAlarm::LEVEL_CRITICAL, 'status' => EquipmentAlarm::CLOSED, 'measured' => 1472, 'hours_ago' => 42, 'note' => '更换主轴轴承并重新校准转速。'],
        ['code' => 'ALM-DEMO-004', 'equipment' => 'EQ-ASM-01', 'metric' => 'pressure', 'level' => EquipmentAlarm::LEVEL_WARNING, 'status' => EquipmentAlarm::CLOSED, 'measured' => 0.83, 'hours_ago' => 38, 'note' => '检查气路接头，紧固后压力稳定。'],
        ['code' => 'ALM-DEMO-005', 'equipment' => 'EQ-TEST-01', 'metric' => 'temperature', 'level' => EquipmentAlarm::LEVEL_INFO, 'status' => EquipmentAlarm::CLOSED, 'measured' => 61.8, 'hours_ago' => 30, 'note' => '加强柜内通风，温度已恢复。'],
        ['code' => 'ALM-DEMO-006', 'equipment' => 'EQ-SMT-01', 'metric' => 'pressure', 'level' => EquipmentAlarm::LEVEL_CRITICAL, 'status' => EquipmentAlarm::CLOSED, 'measured' => 0.91, 'hours_ago' => 22, 'note' => '更换压力传感器并复核采样值。'],
        ['code' => 'ALM-DEMO-007', 'equipment' => 'EQ-CNC-01', 'metric' => 'temperature', 'level' => EquipmentAlarm::LEVEL_WARNING, 'status' => EquipmentAlarm::CLOSED, 'measured' => 65.8, 'hours_ago' => 18, 'note' => '清洗冷却液管路后恢复正常。'],
        ['code' => 'ALM-DEMO-008', 'equipment' => 'EQ-REFLOW-01', 'metric' => 'speed', 'level' => EquipmentAlarm::LEVEL_INFO, 'status' => EquipmentAlarm::CLOSED, 'measured' => 1230, 'hours_ago' => 44, 'note' => '调整传送带张紧度，转速恢复稳定。'],
        ['code' => 'ALM-DEMO-009', 'equipment' => 'EQ-SMT-01', 'metric' => 'speed', 'level' => EquipmentAlarm::LEVEL_INFO, 'status' => EquipmentAlarm::PENDING, 'measured' => 1238, 'hours_ago' => 2],
        ['code' => 'ALM-DEMO-010', 'equipment' => 'EQ-SMT-01', 'metric' => 'temperature', 'level' => EquipmentAlarm::LEVEL_WARNING, 'status' => EquipmentAlarm::CONFIRMED, 'measured' => 65.2, 'hours_ago' => 5],
        ['code' => 'ALM-DEMO-011', 'equipment' => 'EQ-CNC-01', 'metric' => 'pressure', 'level' => EquipmentAlarm::LEVEL_INFO, 'status' => EquipmentAlarm::PENDING, 'measured' => 0.74, 'hours_ago' => 7],
        ['code' => 'ALM-DEMO-012', 'equipment' => 'EQ-ASM-01', 'metric' => 'temperature', 'level' => EquipmentAlarm::LEVEL_CRITICAL, 'status' => EquipmentAlarm::CONFIRMED, 'measured' => 69, 'hours_ago' => 9],
        ['code' => 'ALM-DEMO-013', 'equipment' => 'EQ-ASM-01', 'metric' => 'speed', 'level' => EquipmentAlarm::LEVEL_WARNING, 'status' => EquipmentAlarm::PROCESSING, 'measured' => 1388, 'hours_ago' => 11, 'note' => '已安排停机检查传动机构。'],
        ['code' => 'ALM-DEMO-014', 'equipment' => 'EQ-TEST-01', 'metric' => 'speed', 'level' => EquipmentAlarm::LEVEL_CRITICAL, 'status' => EquipmentAlarm::PENDING, 'measured' => 1466, 'hours_ago' => 13],
        ['code' => 'ALM-DEMO-015', 'equipment' => 'EQ-TEST-01', 'metric' => 'pressure', 'level' => EquipmentAlarm::LEVEL_WARNING, 'status' => EquipmentAlarm::CONFIRMED, 'measured' => 0.82, 'hours_ago' => 15],
        ['code' => 'ALM-DEMO-016', 'equipment' => 'EQ-REFLOW-01', 'metric' => 'pressure', 'level' => EquipmentAlarm::LEVEL_CRITICAL, 'status' => EquipmentAlarm::PROCESSING, 'measured' => 0.88, 'hours_ago' => 18, 'note' => '正在更换比例阀，等待备件到货。'],
        ['code' => 'ALM-DEMO-017', 'equipment' => 'EQ-CNC-01', 'metric' => 'speed', 'level' => EquipmentAlarm::LEVEL_WARNING, 'status' => EquipmentAlarm::CONFIRMED, 'measured' => 1395, 'hours_ago' => 20],
        ['code' => 'ALM-DEMO-018', 'equipment' => 'EQ-SMT-01', 'metric' => 'pressure', 'level' => EquipmentAlarm::LEVEL_WARNING, 'status' => EquipmentAlarm::PROCESSING, 'measured' => 0.82, 'hours_ago' => 24, 'note' => '已降低供料压力，观察中。'],
        ['code' => 'ALM-DEMO-019', 'equipment' => 'EQ-REFLOW-01', 'metric' => 'temperature', 'level' => EquipmentAlarm::LEVEL_CRITICAL, 'status' => EquipmentAlarm::PROCESSING, 'measured' => 68.9, 'hours_ago' => 28, 'note' => '已暂停生产，等待炉膛降温检查。'],
        ['code' => 'ALM-DEMO-020', 'equipment' => 'EQ-ASM-01', 'metric' => 'pressure', 'level' => EquipmentAlarm::LEVEL_INFO, 'status' => EquipmentAlarm::CLOSED, 'measured' => 0.72, 'hours_ago' => 36, 'note' => '密封圈更换后压力恢复正常。'],
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
                    'enabled' => true,
                    'owner_id' => $owner->id,
                ],
            );
        }

        $equipments = Equipment::query()
            ->whereIn('code', array_column(self::ALARMS, 'equipment'))
            ->get()
            ->keyBy('code');
        $handlers = User::query()
            ->whereIn('email', ['equipment1@mes.test', 'equipment2@mes.test'])
            ->get()
            ->keyBy('email');
        $now = now();

        foreach (self::ALARMS as $definition) {
            $equipment = $equipments->get($definition['equipment']);

            if ($equipment === null) {
                throw new RuntimeException('请先执行组织架构 Seeder 再执行 EquipmentSeeder');
            }

            $triggeredAt = $now->copy()->subHours($definition['hours_ago']);
            $handler = $handlers->get($definition['hours_ago'] % 2 === 0 ? 'equipment2@mes.test' : 'equipment1@mes.test');
            $confirmed = in_array($definition['status'], [EquipmentAlarm::CONFIRMED, EquipmentAlarm::PROCESSING, EquipmentAlarm::CLOSED], true);
            $processing = in_array($definition['status'], [EquipmentAlarm::PROCESSING, EquipmentAlarm::CLOSED], true);

            EquipmentAlarm::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'equipment_id' => $equipment->id,
                    'metric' => $definition['metric'],
                    'alarm_code' => EquipmentAlarm::alarmCodeForMetric($definition['metric']),
                    'message' => EquipmentAlarm::messageForMetric($definition['metric']),
                    'level' => $definition['level'],
                    'status' => $definition['status'],
                    'threshold' => EquipmentAlarm::THRESHOLDS[$definition['metric']][$definition['level']],
                    'measured_value' => $definition['measured'],
                    'confirmed_by_id' => $confirmed ? $handler?->id : null,
                    'confirmed_at' => $confirmed ? $triggeredAt->copy()->addMinutes(3) : null,
                    'handled_by_id' => $processing ? $handler?->id : null,
                    'handled_at' => $processing ? $triggeredAt->copy()->addMinutes(10) : null,
                    'handling_note' => $definition['note'] ?? null,
                    'triggered_at' => $triggeredAt,
                    'closed_at' => $definition['status'] === EquipmentAlarm::CLOSED
                        ? $triggeredAt->copy()->addMinutes(30)
                        : null,
                ],
            );
        }

        $this->seedHistory();
    }

    /**
     * An hour of five-minute samples gives the monitor and history pages a
     * curve immediately; restricted devices keep a flat output count because
     * they produced nothing while down.
     */
    private function seedHistory(): void
    {
        $equipments = Equipment::query()
            ->whereIn('code', array_column(self::EQUIPMENTS, 'code'))
            ->orderBy('id')
            ->get();

        foreach ($equipments as $equipmentIndex => $equipment) {
            $baseOutput = 5000 + $equipmentIndex * 1000;
            $producing = $equipment->status === Equipment::RUNNING;
            $start = ($equipment->created_at ?? now())->copy()->startOfHour()->subHour();

            for ($sample = 0; $sample < 12; $sample++) {
                EquipmentData::query()->updateOrCreate(
                    [
                        'equipment_id' => $equipment->id,
                        'recorded_at' => $start->copy()->addMinutes($sample * 5)->format('Y-m-d H:i:s.u'),
                        'message_type' => EquipmentData::TYPE_DATA,
                    ],
                    [
                        'status' => $equipment->status,
                        'temperature' => $equipment->status === Equipment::RUNNING ? 45 + $sample * 0.2 : 28,
                        'speed' => $producing ? 1200 + $sample * 10 : 0,
                        'pressure' => $producing ? 0.6 + $sample * 0.005 : 0,
                        'output' => $producing ? $baseOutput + $sample * 10 : $baseOutput,
                    ],
                );
            }
        }
    }
}
