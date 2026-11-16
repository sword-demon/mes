<?php

namespace App\Modules\Traceability\Service;

use App\Modules\Bom\Model\BomItem;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Material\Service\Quantity;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\Quality\Service\DefectiveProductService;
use App\Modules\Quality\Service\InspectionTaskService;
use App\Modules\Traceability\Data\ForwardTraceData;
use App\Modules\Traceability\Data\TraceMaterialUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Projects the trace from the live production tables, so no snapshot copy can
 * drift away from the order, SN, inspection or defect records it describes.
 */
class ForwardTraceService
{
    public function trace(ProductSn $sn): ForwardTraceData
    {
        $sn->loadMissing($this->relations());

        $processRecords = $sn->processRecords;
        $recordIds = $processRecords->modelKeys();

        $inspections = InspectionTask::query()
            ->with(InspectionTaskService::RELATIONS)
            ->whereIn('process_record_id', $recordIds)
            ->orderBy('id')
            ->get();

        $orderInspections = InspectionTask::query()
            ->with(InspectionTaskService::RELATIONS)
            ->where('production_order_id', $sn->production_order_id)
            ->whereNull('process_record_id')
            ->orderBy('id')
            ->get();

        $defects = DefectiveProduct::query()
            ->with(DefectiveProductService::RELATIONS)
            ->where('production_order_id', $sn->production_order_id)
            ->where('production_batch_id', $sn->production_batch_id)
            ->orderBy('id')
            ->get();

        return new ForwardTraceData(
            $sn,
            $processRecords,
            $inspections,
            $orderInspections,
            $defects,
            $this->materials($sn->production_order_id, $sn->productionOrder->bom->items),
        );
    }

    /**
     * @return array<int|string, string|\Closure>
     */
    private function relations(): array
    {
        return [
            'productionOrder.product',
            'productionOrder.productVersion',
            'productionOrder.productionLine',
            'productionOrder.bom.items' => fn (HasMany $query) => $query->orderBy('id'),
            'productionOrder.bom.items.material',
            'productionOrder.route.operations.process',
            'productionBatch',
            'employee',
            'equipment',
            'processRecords.routeOperation.process',
            'processRecords.employee',
            'processRecords.team',
            'processRecords.equipment',
        ];
    }

    /**
     * BOM consumption with the quantity actually issued for this order.
     *
     * @param  Collection<int, BomItem>  $items
     * @return array<int, TraceMaterialUsage>
     */
    private function materials(int $orderId, Collection $items): array
    {
        $issued = MaterialRequisitionItem::query()
            ->whereHas('requisition', fn (Builder $query) => $query->where('production_order_id', $orderId))
            ->get(['material_id', 'issued_quantity'])
            ->groupBy('material_id')
            ->map(fn (Collection $rows): string => Quantity::toDecimal(
                $rows->sum(fn (MaterialRequisitionItem $row): int => Quantity::toMilli($row->issued_quantity)),
            ));

        return $items
            ->map(fn (BomItem $item): TraceMaterialUsage => new TraceMaterialUsage(
                $item->material,
                $item->quantity,
                $item->loss_rate,
                $issued->get($item->material_id, '0.000'),
            ))
            ->values()
            ->all();
    }
}
