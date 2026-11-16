<?php

namespace Database\Seeders;

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Inventory\Service\InventoryService;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Material\Service\Quantity;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use App\Modules\System\Model\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TraceabilitySeeder extends Seeder
{
    private const EXECUTABLE_STATUSES = [
        ProductionOrder::RELEASED,
        ProductionOrder::IN_PROGRESS,
        ProductionOrder::PAUSED,
        ProductionOrder::COMPLETED,
        ProductionOrder::CLOSED,
    ];

    public function __construct(private readonly InventoryService $inventories) {}

    public function run(): void
    {
        $operator = User::query()->where('email', 'admin@mes.test')->first();

        if ($operator === null) {
            throw new RuntimeException('请先执行 SystemSeeder 再执行 TraceabilitySeeder');
        }

        $beforeBalances = Inventory::query()->pluck('current_quantity', 'id');

        DB::transaction(function () use ($operator, $beforeBalances): void {
            ProductionOrder::query()
                ->with('bom.items')
                ->whereIn('status', self::EXECUTABLE_STATUSES)
                ->orderBy('id')
                ->each(fn (ProductionOrder $order) => $this->seedRequisition($order, $operator->id));

            $this->bindSerialNumbers();
            $this->restoreBalances($beforeBalances, $operator->id);
        });
    }

    private function seedRequisition(ProductionOrder $order, int $operatorId): void
    {
        if ($order->bom === null) {
            return;
        }

        $requisition = MaterialRequisition::query()->updateOrCreate(
            ['code' => 'MR-'.$order->code],
            [
                'production_order_id' => $order->id,
                'status' => MaterialRequisition::ISSUED,
                'remark' => '演示领料',
                'created_by_id' => $operatorId,
            ],
        );

        $plannedMilli = Quantity::toMilli($order->planned_quantity);

        foreach ($order->bom->items as $item) {
            $issuedMilli = intdiv($plannedMilli * Quantity::toMilli($item->quantity), 1000);
            $issued = Quantity::toDecimal($issuedMilli);

            MaterialRequisitionItem::query()->updateOrCreate(
                [
                    'requisition_id' => $requisition->id,
                    'material_id' => $item->material_id,
                ],
                [
                    'requested_quantity' => $issued,
                    'issued_quantity' => $issued,
                    'over_requirement' => false,
                ],
            );

            $alreadyIssued = InventoryTransaction::query()
                ->where('business_type', InventoryTransaction::OUTBOUND)
                ->where('reference_no', $requisition->code)
                ->where('material_id', $item->material_id)
                ->exists();

            if (! $alreadyIssued) {
                $this->issueFromStock($item->material_id, $issuedMilli, $requisition->code, $operatorId);
            }
        }
    }

    private function issueFromStock(int $materialId, int $requiredMilli, string $referenceNo, int $operatorId): void
    {
        $remaining = $requiredMilli;
        $inventories = Inventory::query()
            ->with('location')
            ->where('material_id', $materialId)
            ->orderByDesc('current_quantity')
            ->orderBy('id')
            ->get();

        foreach ($inventories as $inventory) {
            if ($remaining <= 0) {
                break;
            }

            $available = Quantity::toMilli($inventory->current_quantity)
                - Quantity::toMilli($inventory->frozen_quantity);

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);

            $this->inventories->outbound([
                'material_id' => $materialId,
                'warehouse_id' => $inventory->location->warehouse_id,
                'location_id' => $inventory->location_id,
                'batch_no' => $inventory->batch_no,
                'quantity' => Quantity::toFloat($take),
                'reference_no' => $referenceNo,
                'remark' => '生产领料',
            ], $operatorId);

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new RuntimeException("物料 {$materialId} 库存不足，无法生成领料流水");
        }
    }

    /**
     * Demo stock starts from the same balances on every run; the outbound rows
     * stay as traceability evidence and a calibration row closes the ledger.
     *
     * @param  Collection<int, string>  $beforeBalances
     */
    private function restoreBalances(Collection $beforeBalances, int $operatorId): void
    {
        foreach ($beforeBalances as $inventoryId => $before) {
            $inventory = Inventory::query()->with('location')->find($inventoryId);

            if ($inventory === null
                || $inventory->location === null
                || Quantity::toMilli($inventory->current_quantity) === Quantity::toMilli($before)) {
                continue;
            }

            $this->inventories->adjust([
                'material_id' => $inventory->material_id,
                'warehouse_id' => $inventory->location->warehouse_id,
                'location_id' => $inventory->location_id,
                'batch_no' => $inventory->batch_no,
                'current_quantity' => $before,
                'reference_no' => 'SEED-TRACE-'.$inventoryId,
                'remark' => '追溯演示账实校准',
            ], $operatorId);
        }
    }

    /**
     * One SN per operation keeps the task+operation unique index intact while
     * giving every active unit a concrete process history to trace.
     */
    private function bindSerialNumbers(): void
    {
        $orders = ProductionOrder::query()
            ->whereIn('status', self::EXECUTABLE_STATUSES)
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $tasks = ProductionTask::query()
                ->where('production_order_id', $order->id)
                ->get()
                ->keyBy('id');
            $records = ProcessRecord::query()
                ->whereIn('production_task_id', $tasks->keys())
                ->where('sequence', '>=', 2)
                ->with('routeOperation.process')
                ->orderBy('sequence')
                ->orderBy('id')
                ->get();
            $sns = ProductSn::query()
                ->where('production_order_id', $order->id)
                ->where('status', ProductSn::ACTIVE)
                ->orderBy('id')
                ->get();

            foreach ($records as $index => $record) {
                $sn = $sns->get($index % max(1, $sns->count()));
                $task = $tasks->get($record->production_task_id);

                if ($sn === null || $task === null) {
                    continue;
                }

                $requiresInspection = $record->requiresInspection();
                $completedAt = now()->subDays(2)->addHour();

                $record->update([
                    'sn' => $sn->sn,
                    'status' => $requiresInspection ? ProcessRecord::INSPECTED : ProcessRecord::TRANSFERRED,
                    'employee_id' => $record->employee_id ?? $task->owner_id,
                    'team_id' => $record->team_id ?? $task->team_id,
                    'started_at' => $record->started_at ?? now()->subDays(2),
                    'completed_at' => $record->completed_at ?? $completedAt,
                    'inspected_at' => $requiresInspection ? ($record->inspected_at ?? $completedAt->addMinutes(30)) : null,
                    'submitted_at' => $requiresInspection ? null : ($record->submitted_at ?? now()->subDays(2)->addHours(2)),
                    'received_at' => $requiresInspection ? null : ($record->received_at ?? now()->subDays(2)->addHours(3)),
                ]);
            }
        }
    }
}
