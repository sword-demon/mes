<?php

namespace Database\Seeders;

use App\Modules\Equipment\Model\Equipment;
use App\Modules\Equipment\Model\EquipmentData;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Material\Service\Quantity;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionReport;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use RuntimeException;

class DemoDataConsistencyChecker
{
    public function assertConsistent(): void
    {
        $this->assertReportQuantities();
        $this->assertOrderProducedQuantities();
        $this->assertTaskPlannedQuantities();
        $this->assertInventoryLedger();
        $this->assertSerialNumbers();
        $this->assertEquipmentOutput();
    }

    private function assertReportQuantities(): void
    {
        ProductionReport::query()->each(function (ProductionReport $report): void {
            $balanced = Quantity::toMilli($report->qualified_quantity) + Quantity::toMilli($report->defective_quantity)
                === Quantity::toMilli($report->quantity);

            $this->ensure($balanced, "报工 {$report->code} 的合格数 + 不良数不等于报工数");
        });
    }

    private function assertOrderProducedQuantities(): void
    {
        $reportedByOrder = ProductionReport::query()
            ->join('mes_production_tasks as tasks', 'tasks.id', '=', 'mes_production_reports.production_task_id')
            ->where('mes_production_reports.type', '<>', ProductionReport::REVERSED)
            ->selectRaw('tasks.production_order_id, SUM(mes_production_reports.quantity) as reported_quantity')
            ->groupBy('tasks.production_order_id')
            ->pluck('reported_quantity', 'production_order_id');

        ProductionOrder::query()->each(function (ProductionOrder $order) use ($reportedByOrder): void {
            $this->ensure(
                Quantity::toMilli($reportedByOrder[$order->id] ?? 0) === Quantity::toMilli($order->produced_quantity),
                "订单 {$order->code} 的已生产数不等于其下有效报工之和",
            );
        });
    }

    private function assertTaskPlannedQuantities(): void
    {
        $reported = ProductionReport::query()
            ->where('type', '<>', ProductionReport::REVERSED)
            ->get()
            ->groupBy('production_task_id')
            ->map(fn ($reports): int => (int) $reports->sum(fn (ProductionReport $report): int => Quantity::toMilli($report->quantity)));

        ProductionTask::query()->each(function (ProductionTask $task) use ($reported): void {
            $this->ensure(
                ($reported[$task->id] ?? 0) <= Quantity::toMilli($task->planned_quantity),
                "任务 {$task->code} 的有效报工数超过计划数",
            );
        });
    }

    private function assertInventoryLedger(): void
    {
        $nets = InventoryTransaction::query()
            ->get()
            ->groupBy('inventory_id')
            ->map(fn ($transactions): int => (int) $transactions->sum(fn (InventoryTransaction $transaction): int => Quantity::toMilli($transaction->quantity)));

        Inventory::query()->each(function (Inventory $inventory) use ($nets): void {
            $this->ensure(
                ($nets[$inventory->id] ?? 0) === Quantity::toMilli($inventory->current_quantity),
                "库存 {$inventory->id} 的流水净额不等于当前库存",
            );
        });
    }

    private function assertSerialNumbers(): void
    {
        $total = ProductSn::query()->count();
        $distinct = ProductSn::query()->distinct()->count('sn');

        $this->ensure($total === $distinct, 'SN 存在重复编号');

        ProductSn::query()->with('productionBatch')->each(function (ProductSn $sn): void {
            $batch = $sn->productionBatch;

            if ($batch === null) {
                $this->fail("SN {$sn->sn} 关联的生产批次不存在");
            }

            $this->ensure(
                $batch->production_order_id === $sn->production_order_id,
                "SN {$sn->sn} 的生产批次不属于其生产订单",
            );
        });
    }

    private function assertEquipmentOutput(): void
    {
        $samples = EquipmentData::query()
            ->with('equipment:id,code')
            ->where('message_type', EquipmentData::TYPE_DATA)
            ->orderBy('equipment_id')
            ->orderBy('recorded_at')
            ->get(['equipment_id', 'recorded_at', 'status', 'output']);
        $previousOutput = [];

        foreach ($samples as $sample) {
            $previous = $previousOutput[$sample->equipment_id] ?? null;

            if ($previous !== null && in_array($sample->status, [Equipment::REPAIR, Equipment::OFFLINE], true)) {
                $equipmentCode = $sample->equipment->code;

                $this->ensure(
                    (int) $sample->output <= $previous,
                    "设备 {$equipmentCode} 在维修/离线状态下存在产量增长数据",
                );
            }

            $previousOutput[$sample->equipment_id] = (int) $sample->output;
        }
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            $this->fail($message);
        }
    }

    private function fail(string $message): never
    {
        throw new RuntimeException('Seeder 一致性自检失败：'.$message);
    }
}
