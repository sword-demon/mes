<?php

namespace App\Modules\Traceability\Service;

use App\Modules\Bom\Model\BomItem;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Material\Service\Quantity;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Production\Resource\ProcessRecordResource;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\Quality\Resource\DefectiveProductResource;
use App\Modules\Quality\Resource\InspectionTaskResource;
use App\Modules\Quality\Service\DefectiveProductService;
use App\Modules\Quality\Service\InspectionTaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ForwardTraceService
{
    private const array SN_RELATIONS = [
        'productionOrder.product',
        'productionOrder.productVersion',
        'productionOrder.productionLine',
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

    /**
     * @return array<string, mixed>
     */
    public function trace(ProductSn $sn): array
    {
        $sn->loadMissing(self::SN_RELATIONS);

        $order = $sn->productionOrder;
        $recordIds = $sn->processRecords->modelKeys();
        $hasData = $sn->processRecords->isNotEmpty();

        $inspections = InspectionTask::query()
            ->with(InspectionTaskService::RELATIONS)
            ->where('production_order_id', $order->id)
            ->where(fn (Builder $query) => $query
                ->whereIn('process_record_id', $recordIds)
                ->orWhereNull('process_record_id'))
            ->orderBy('id')
            ->get();

        $defects = DefectiveProduct::query()
            ->with(DefectiveProductService::RELATIONS)
            ->where('production_order_id', $order->id)
            ->where('production_batch_id', $sn->production_batch_id)
            ->orderBy('id')
            ->get();

        return [
            'has_data' => $hasData,
            'message' => $hasData ? null : '该 SN 暂无可追溯的生产执行记录',
            'sn' => [
                'id' => $sn->id,
                'sn' => $sn->sn,
                'status' => $sn->status,
                'status_label' => $sn->statusLabel(),
                'material_batch_no' => $sn->material_batch_no,
                'printed_at' => $sn->printed_at,
                'voided_at' => $sn->voided_at,
                'void_reason' => $sn->void_reason,
                'employee' => $sn->employee === null ? null : [
                    'id' => $sn->employee->id,
                    'employee_no' => $sn->employee->employee_no,
                    'name' => $sn->employee->name,
                ],
                'equipment' => $sn->equipment === null ? null : [
                    'id' => $sn->equipment->id,
                    'code' => $sn->equipment->code,
                    'name' => $sn->equipment->name,
                    'type' => $sn->equipment->type,
                    'status' => $sn->equipment->status,
                    'status_label' => $sn->equipment->statusLabel(),
                ],
                'created_at' => $sn->created_at,
            ],
            'product' => [
                'id' => $order->product->id,
                'code' => $order->product->code,
                'name' => $order->product->name,
                'model' => $order->product->model,
                'specification' => $order->product->specification,
                'category' => $order->product->category,
                'unit' => $order->product->unit,
            ],
            'production_order' => [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status,
                'status_label' => $order->statusLabel(),
                'planned_quantity' => $order->planned_quantity,
                'produced_quantity' => $order->produced_quantity,
                'qualified_quantity' => $order->qualified_quantity,
                'defective_quantity' => $order->defective_quantity,
                'planned_start_at' => $order->planned_start_at,
                'planned_end_at' => $order->planned_end_at,
                'bom_version' => $order->bom_version,
                'route_version' => $order->route_version,
                'production_line' => [
                    'id' => $order->productionLine->id,
                    'code' => $order->productionLine->code,
                    'name' => $order->productionLine->name,
                ],
                'product_version' => [
                    'id' => $order->productVersion->id,
                    'version' => $order->productVersion->version,
                ],
            ],
            'production_batch' => [
                'id' => $sn->productionBatch->id,
                'code' => $sn->productionBatch->code,
                'quantity' => $sn->productionBatch->quantity,
            ],
            'bom' => [
                'id' => $order->bom->id,
                'code' => $order->bom->code,
                'version' => $order->bom->version,
                'audit_status' => $order->bom->audit_status,
            ],
            'materials' => $this->materials($order->id, $order->bom->items),
            'route' => [
                'id' => $order->route->id,
                'code' => $order->route->code,
                'version' => $order->route->version,
                'audit_status' => $order->route->audit_status,
                'operations' => $order->route->operations
                    ->map(fn ($operation): array => [
                        'id' => $operation->id,
                        'sequence' => $operation->sequence,
                        'standard_hours' => $operation->standard_hours,
                        'parameters' => $operation->parameters,
                        'process' => [
                            'id' => $operation->process->id,
                            'code' => $operation->process->code,
                            'name' => $operation->process->name,
                            'requires_inspection' => $operation->process->requires_inspection,
                        ],
                    ])
                    ->values()
                    ->all(),
            ],
            'process_records' => ProcessRecordResource::collection($sn->processRecords)->resolve(),
            'inspections' => collect(InspectionTaskResource::collection($inspections)->resolve())
                ->map(fn (array $inspection): array => [
                    ...$inspection,
                    'scope' => $inspection['process_record'] === null ? 'order' : 'sn',
                ])
                ->values()
                ->all(),
            'defects' => collect(DefectiveProductResource::collection($defects)->resolve())
                ->map(fn (array $defect): array => [...$defect, 'scope' => 'batch'])
                ->values()
                ->all(),
        ];
    }

    /**
     * BOM consumption with the quantity actually issued for this order.
     *
     * @param  Collection<int, BomItem>  $items
     * @return array<int, array<string, mixed>>
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
            ->map(fn (BomItem $item): array => [
                'material' => $this->materialRef($item->material),
                'quantity' => $item->quantity,
                'loss_rate' => $item->loss_rate,
                'issued_quantity' => $issued->get($item->material_id, '0.000'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function materialRef(Material $material): array
    {
        return [
            'id' => $material->id,
            'code' => $material->code,
            'name' => $material->name,
            'unit' => $material->unit,
            'batch_managed' => $material->batch_managed,
        ];
    }
}
