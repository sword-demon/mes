<?php

namespace App\Modules\Traceability\Service;

use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Service\Quantity;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Traceability\Data\BackwardTraceBatchData;
use App\Modules\Traceability\Data\BackwardTraceData;
use App\Modules\Traceability\Data\BackwardTraceMaterialUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Rebuilds the material-to-product relation from the material-issue ledger, so the
 * trace never depends on a hand-maintained snapshot. Issues are recorded per
 * order, so every SN-bearing batch of an affected order is in scope.
 */
class BackwardTraceService
{
    public function trace(string $batchNo): BackwardTraceData
    {
        $transactions = InventoryTransaction::query()
            ->with('material')
            ->where('business_type', InventoryTransaction::OUTBOUND)
            ->whereNotNull('reference_no')
            ->whereHas('inventory', fn (Builder $query): Builder => $query->where('batch_no', $batchNo))
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            return new BackwardTraceData($batchNo, [], collect());
        }

        $requisitions = MaterialRequisition::query()
            ->with('productionOrder.product')
            ->whereIn('code', $transactions->pluck('reference_no')->unique()->values())
            ->get()
            ->keyBy('code');

        /** @var array<int, array{order: ProductionOrder, materials: array<int, array{material: Material, issued_milli: int}>}> $usage */
        $usage = [];

        foreach ($transactions as $transaction) {
            $requisition = $requisitions->get($transaction->reference_no);

            if (! $requisition instanceof MaterialRequisition || $requisition->productionOrder === null) {
                continue;
            }

            $orderId = $requisition->production_order_id;
            $usage[$orderId] ??= [
                'order' => $requisition->productionOrder,
                'materials' => [],
            ];
            $usage[$orderId]['materials'][$transaction->material_id] ??= [
                'material' => $transaction->material,
                'issued_milli' => 0,
            ];
            $usage[$orderId]['materials'][$transaction->material_id]['issued_milli'] -= Quantity::toMilli($transaction->quantity);
        }

        if ($usage === []) {
            return new BackwardTraceData($batchNo, [], collect());
        }

        $batchesByOrder = ProductionBatch::query()
            ->with(['productSns' => fn (HasMany $query) => $query->orderBy('id')])
            ->whereIn('production_order_id', array_keys($usage))
            ->orderBy('id')
            ->get()
            ->groupBy('production_order_id');

        $rows = [];

        foreach ($usage as $orderUsage) {
            $materialUsages = collect($orderUsage['materials'])
                ->map(fn (array $usage): BackwardTraceMaterialUsage => new BackwardTraceMaterialUsage(
                    $usage['material'],
                    Quantity::toDecimal($usage['issued_milli']),
                ))
                ->values();

            foreach ($batchesByOrder->get($orderUsage['order']->id, collect()) as $batch) {
                if ($batch->productSns->isEmpty()) {
                    continue;
                }

                $rows[] = new BackwardTraceBatchData(
                    $batch,
                    $orderUsage['order'],
                    $materialUsages,
                    $batch->productSns,
                );
            }
        }

        /** @var Collection<int, BackwardTraceBatchData> $rows */
        $rows = collect($rows);

        return new BackwardTraceData(
            $batchNo,
            $rows->flatMap(fn (BackwardTraceBatchData $row) => $row->materials)
                ->map(fn (BackwardTraceMaterialUsage $usage): Material => $usage->material)
                ->unique('id')
                ->values()
                ->all(),
            $rows,
        );
    }
}
