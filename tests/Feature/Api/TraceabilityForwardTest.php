<?php

use App\Modules\Bom\Model\BomItem;
use App\Modules\Equipment\Model\Equipment;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Organization\Model\Employee;
use App\Modules\Process\Model\Process;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductionTask;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\System\Model\User;

/**
 * @return array<string, mixed>
 */
function forwardTraceCase(): array
{
    $order = ProductionOrder::factory()->create([
        'status' => ProductionOrder::IN_PROGRESS,
        'planned_quantity' => 10,
    ]);
    $material = Material::factory()->create(['code' => 'MAT-TRACE-01', 'name' => '追溯物料']);
    BomItem::factory()->create([
        'bom_id' => $order->bom_id,
        'material_id' => $material->id,
        'quantity' => 2,
    ]);
    $secondMaterial = Material::factory()->create(['code' => 'MAT-TRACE-02', 'name' => '追溯辅料']);
    BomItem::factory()->create([
        'bom_id' => $order->bom_id,
        'material_id' => $secondMaterial->id,
        'quantity' => 1,
    ]);

    $stitching = Process::factory()->create(['name' => 'SMT 贴片', 'requires_inspection' => true]);
    $assembly = Process::factory()->create(['name' => '整机组装']);
    $stitchingOperation = RouteOperation::factory()->create([
        'route_id' => $order->route_id,
        'process_id' => $stitching->id,
        'sequence' => 1,
    ]);
    $assemblyOperation = RouteOperation::factory()->create([
        'route_id' => $order->route_id,
        'process_id' => $assembly->id,
        'sequence' => 2,
    ]);

    $task = ProductionTask::factory()->create(['production_order_id' => $order->id]);
    $batch = ProductionBatch::factory()->create([
        'production_order_id' => $order->id,
        'quantity' => 5,
    ]);
    $employee = Employee::factory()->create(['employee_no' => 'EMP-TRACE', 'name' => '追溯操作员']);
    $equipment = Equipment::factory()->create(['code' => 'EQ-TRACE']);
    $sn = ProductSn::factory()->create([
        'production_order_id' => $order->id,
        'production_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'equipment_id' => $equipment->id,
        'material_batch_no' => 'MAT-LOT-TRACE',
    ]);
    $record = ProcessRecord::factory()->create([
        'production_task_id' => $task->id,
        'route_operation_id' => $stitchingOperation->id,
        'sequence' => 1,
        'sn' => $sn->sn,
        'employee_id' => $employee->id,
        'equipment_id' => $equipment->id,
        'status' => ProcessRecord::TRANSFERRED,
        'started_at' => now()->subHours(2),
        'completed_at' => now()->subHour(),
        'received_at' => now()->subMinutes(30),
    ]);

    $otherSn = ProductSn::factory()->create([
        'production_order_id' => $order->id,
        'production_batch_id' => $batch->id,
    ]);
    $otherTask = ProductionTask::factory()->create(['production_order_id' => $order->id]);
    $otherRecord = ProcessRecord::factory()->create([
        'production_task_id' => $otherTask->id,
        'route_operation_id' => $stitchingOperation->id,
        'sn' => $otherSn->sn,
        'status' => ProcessRecord::TRANSFERRED,
    ]);

    $item = InspectionItem::factory()->create(['code' => 'INS-TRACE', 'name' => '焊点温度']);
    $ipqc = InspectionTask::factory()->create([
        'production_order_id' => $order->id,
        'process_record_id' => $record->id,
        'route_operation_id' => $stitchingOperation->id,
        'inspection_type' => InspectionTask::IPQC,
        'result' => InspectionTask::PASSED,
        'inspected_at' => now()->subMinutes(45),
    ]);
    $ipqc->results()->create([
        'inspection_item_id' => $item->id,
        'measured_value' => 5,
        'standard_value' => 5,
        'min_value' => 4.8,
        'max_value' => 5.2,
        'passed' => true,
    ]);
    $otherIpqc = InspectionTask::factory()->create([
        'production_order_id' => $order->id,
        'process_record_id' => $otherRecord->id,
        'route_operation_id' => $stitchingOperation->id,
        'inspection_type' => InspectionTask::IPQC,
        'result' => InspectionTask::FAILED,
    ]);
    $oqc = InspectionTask::factory()->create([
        'production_order_id' => $order->id,
        'route_operation_id' => $assemblyOperation->id,
        'inspection_type' => InspectionTask::OQC,
    ]);
    $otherOrder = ProductionOrder::factory()->create();
    InspectionTask::factory()->create([
        'production_order_id' => $otherOrder->id,
        'route_operation_id' => RouteOperation::factory()->create([
            'route_id' => $otherOrder->route_id,
        ])->id,
        'inspection_type' => InspectionTask::OQC,
    ]);

    $defect = DefectiveProduct::factory()->create([
        'production_order_id' => $order->id,
        'route_operation_id' => $stitchingOperation->id,
        'production_batch_id' => $batch->id,
        'quantity' => 2,
    ]);
    $otherBatch = ProductionBatch::factory()->create(['production_order_id' => $order->id]);
    DefectiveProduct::factory()->create([
        'production_order_id' => $order->id,
        'route_operation_id' => $stitchingOperation->id,
        'production_batch_id' => $otherBatch->id,
        'quantity' => 1,
    ]);

    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-TRACE-01',
        'production_order_id' => $order->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => User::factory()->create()->id,
    ]);
    MaterialRequisitionItem::query()->create([
        'requisition_id' => $requisition->id,
        'material_id' => $material->id,
        'requested_quantity' => 20,
        'issued_quantity' => 18,
    ]);
    $otherRequisition = MaterialRequisition::query()->create([
        'code' => 'MR-TRACE-02',
        'production_order_id' => $otherOrder->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $requisition->created_by_id,
    ]);
    MaterialRequisitionItem::query()->create([
        'requisition_id' => $otherRequisition->id,
        'material_id' => $material->id,
        'requested_quantity' => 100,
        'issued_quantity' => 99,
    ]);

    return compact(
        'order',
        'material',
        'stitching',
        'assembly',
        'employee',
        'equipment',
        'sn',
        'otherSn',
        'ipqc',
        'oqc',
        'otherIpqc',
        'defect',
    );
}

/**
 * @return array<int, string>
 */
function forwardTracePermissions(): array
{
    return ['traceability.forward.show'];
}

it('returns 200 with the complete forward trace of a serial number', function () {
    $case = forwardTraceCase();
    $actor = userWithPermissions(forwardTracePermissions());

    $response = bearer($actor)
        ->getJson("/api/v1/traceability/sn/{$case['sn']->sn}")
        ->assertOk()
        ->assertJsonPath('data.has_data', true)
        ->assertJsonPath('data.message', null)
        ->assertJsonPath('data.sn.sn', $case['sn']->sn)
        ->assertJsonPath('data.sn.status', ProductSn::ACTIVE)
        ->assertJsonPath('data.sn.material_batch_no', 'MAT-LOT-TRACE')
        ->assertJsonPath('data.sn.employee.name', '追溯操作员')
        ->assertJsonPath('data.sn.equipment.code', 'EQ-TRACE')
        ->assertJsonPath('data.product.code', $case['order']->product->code)
        ->assertJsonPath('data.production_order.code', $case['order']->code)
        ->assertJsonPath('data.production_order.status', ProductionOrder::IN_PROGRESS)
        ->assertJsonPath('data.production_batch.code', ProductionBatch::query()
            ->whereKey($case['sn']->production_batch_id)
            ->value('code'))
        ->assertJsonPath('data.bom.version', $case['order']->bom_version)
        ->assertJsonPath('data.materials.0.material.code', 'MAT-TRACE-01')
        ->assertJsonPath('data.materials.1.material.code', 'MAT-TRACE-02')
        ->assertJsonPath('data.materials.0.issued_quantity', '18.000')
        ->assertJsonPath('data.route.version', $case['order']->route_version)
        ->assertJsonPath('data.route.operations.0.process.name', 'SMT 贴片')
        ->assertJsonPath('data.route.operations.1.process.name', '整机组装')
        ->assertJsonCount(1, 'data.process_records')
        ->assertJsonPath('data.process_records.0.sn', $case['sn']->sn)
        ->assertJsonPath('data.process_records.0.employee.name', '追溯操作员')
        ->assertJsonPath('data.process_records.0.equipment.code', 'EQ-TRACE')
        ->assertJsonPath('data.process_records.0.route_operation.process.name', 'SMT 贴片')
        ->assertJsonCount(1, 'data.inspections')
        ->assertJsonPath('data.inspections.0.id', $case['ipqc']->id)
        ->assertJsonPath('data.inspections.0.process_record.id', $case['ipqc']->process_record_id)
        ->assertJsonPath('data.inspections.0.scope', 'sn')
        ->assertJsonPath('data.inspections.0.items.0.code', 'INS-TRACE')
        ->assertJsonPath('data.inspections.0.items.0.measured_value', '5.000')
        ->assertJsonCount(1, 'data.order_inspections')
        ->assertJsonPath('data.order_inspections.0.id', $case['oqc']->id)
        ->assertJsonPath('data.order_inspections.0.inspection_type', InspectionTask::OQC)
        ->assertJsonPath('data.order_inspections.0.scope', 'order')
        ->assertJsonPath('data.data_granularity.materials', 'order')
        ->assertJsonPath('data.data_granularity.inspections', 'sn')
        ->assertJsonPath('data.data_granularity.order_inspections', 'order')
        ->assertJsonPath('data.data_granularity.defects', 'batch')
        ->assertJsonPath('data.defects.0.scope', 'batch')
        ->assertJsonPath('data.defects.0.defect_code.name', $case['defect']->defectCode->name);

    $processRecordIds = collect($response->json('data.process_records'))->pluck('id');
    $defectIds = collect($response->json('data.defects'))->pluck('id');
    $inspectionIds = collect($response->json('data.inspections'))->pluck('id');
    $orderInspectionIds = collect($response->json('data.order_inspections'))->pluck('id');

    expect($processRecordIds)->not->toContain(
        ProcessRecord::query()->where('sn', $case['otherSn']->sn)->value('id'),
    )->and($defectIds)->toHaveCount(1)
        ->and($inspectionIds)->not->toContain($case['otherIpqc']->id)
        ->and($orderInspectionIds)->not->toContain($case['otherIpqc']->id)
        ->and($response->json('data.materials.0.issued_quantity'))->toBe('18.000');
});

it('returns 404 with a clear message for an unknown serial number', function () {
    $actor = userWithPermissions(forwardTracePermissions());

    bearer($actor)
        ->getJson('/api/v1/traceability/sn/UNKNOWN-SN')
        ->assertNotFound()
        ->assertJsonPath('code', 404)
        ->assertJsonPath('message', 'SN 不存在');
});

it('returns 200 and reports a serial number without execution records as missing production data', function () {
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::RELEASED]);
    $sn = ProductSn::factory()->create(['production_order_id' => $order->id]);
    $actor = userWithPermissions(forwardTracePermissions());

    bearer($actor)
        ->getJson("/api/v1/traceability/sn/{$sn->sn}")
        ->assertOk()
        ->assertJsonPath('data.has_data', false)
        ->assertJsonPath('data.message', '该 SN 尚无绑定 SN 的工序执行记录，以下为订单与批次信息')
        ->assertJsonCount(0, 'data.process_records')
        ->assertJsonPath('data.production_order.id', $order->id);
});

it('returns 403 without the forward trace permission', function () {
    $case = forwardTraceCase();
    $actor = userWithPermissions(['production.sns.index']);

    bearer($actor)
        ->getJson("/api/v1/traceability/sn/{$case['sn']->sn}")
        ->assertForbidden();
});

it('returns 401 when the caller is unauthenticated', function () {
    $this->getJson('/api/v1/traceability/sn/UNKNOWN-SN')
        ->assertUnauthorized();
});
