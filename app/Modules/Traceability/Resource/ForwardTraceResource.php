<?php

namespace App\Modules\Traceability\Resource;

use App\Modules\Material\Model\Material;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Resource\ProcessRecordResource;
use App\Modules\Quality\Resource\DefectiveProductResource;
use App\Modules\Quality\Resource\InspectionTaskResource;
use App\Modules\Traceability\Data\ForwardTraceData;
use App\Modules\Traceability\Data\TraceMaterialUsage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForwardTraceResource extends JsonResource
{
    public function __construct(private readonly ForwardTraceData $trace)
    {
        parent::__construct($trace);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sn = $this->trace->sn;
        $order = $sn->productionOrder;
        $hasData = $this->trace->processRecords->isNotEmpty();

        return [
            'has_data' => $hasData,
            'message' => $hasData ? null : '该 SN 尚无绑定 SN 的工序执行记录，以下为订单与批次信息',
            'data_granularity' => [
                'materials' => 'order',
                'inspections' => 'sn',
                'order_inspections' => 'order',
                'defects' => 'batch',
            ],
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
            'materials' => array_map(
                fn (TraceMaterialUsage $usage): array => [
                    'material' => $this->materialRef($usage->material),
                    'quantity' => $usage->quantity,
                    'loss_rate' => $usage->lossRate,
                    'issued_quantity' => $usage->issuedQuantity,
                    'scope' => 'order',
                ],
                $this->trace->materials,
            ),
            'route' => [
                'id' => $order->route->id,
                'code' => $order->route->code,
                'version' => $order->route->version,
                'audit_status' => $order->route->audit_status,
                'operations' => $order->route->operations
                    ->map(fn (RouteOperation $operation): array => [
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
            'process_records' => ProcessRecordResource::collection($this->trace->processRecords)->resolve(),
            'inspections' => $this->inspectionRows(
                InspectionTaskResource::collection($this->trace->inspections)->resolve(),
                'sn',
            ),
            'order_inspections' => $this->inspectionRows(
                InspectionTaskResource::collection($this->trace->orderInspections)->resolve(),
                'order',
            ),
            'defects' => collect(DefectiveProductResource::collection($this->trace->defects)->resolve())
                ->map(fn (array $defect): array => [...$defect, 'scope' => 'batch'])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function inspectionRows(array $rows, string $scope): array
    {
        return collect($rows)
            ->map(fn (array $inspection): array => [...$inspection, 'scope' => $scope])
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
