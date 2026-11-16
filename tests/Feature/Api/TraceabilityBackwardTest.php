<?php

use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductSn;
use App\Modules\System\Model\User;

/**
 * @return array<string, mixed>
 */
function backwardTraceCase(): array
{
    [, $location] = inventoryLocation('WH-TRACE', 'T-01');
    $userId = User::factory()->create()->id;
    $material = Material::factory()->create([
        'code' => 'MAT-BATCH-01',
        'name' => '批次物料',
        'unit' => 'kg',
        'batch_managed' => true,
    ]);
    $order = ProductionOrder::factory()->create(['status' => ProductionOrder::IN_PROGRESS]);
    $otherOrder = ProductionOrder::factory()->create(['status' => ProductionOrder::IN_PROGRESS]);
    $unrelatedOrder = ProductionOrder::factory()->create(['status' => ProductionOrder::IN_PROGRESS]);

    $inventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => 'LOT-BATCH-001',
        'current_quantity' => 100,
    ]);
    $unrelatedInventory = Inventory::factory()->create([
        'material_id' => $material->id,
        'location_id' => $location->id,
        'batch_no' => 'LOT-BATCH-999',
        'current_quantity' => 100,
    ]);

    $requisition = MaterialRequisition::query()->create([
        'code' => 'MR-BATCH-01',
        'production_order_id' => $order->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $userId,
    ]);
    MaterialRequisitionItem::query()->create([
        'requisition_id' => $requisition->id,
        'material_id' => $material->id,
        'requested_quantity' => 20,
        'issued_quantity' => 18,
    ]);
    InventoryTransaction::query()->create([
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -10,
        'before_quantity' => 100,
        'after_quantity' => 90,
        'reference_no' => $requisition->code,
        'remark' => '生产发料',
        'created_by_id' => $userId,
    ]);
    InventoryTransaction::query()->create([
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -8,
        'before_quantity' => 90,
        'after_quantity' => 82,
        'reference_no' => $requisition->code,
        'remark' => '生产发料',
        'created_by_id' => $userId,
    ]);
    InventoryTransaction::query()->create([
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::INBOUND,
        'quantity' => 2,
        'before_quantity' => 82,
        'after_quantity' => 84,
        'reference_no' => 'RT-BATCH-01',
        'remark' => '生产退料',
        'created_by_id' => $userId,
    ]);

    $otherRequisition = MaterialRequisition::query()->create([
        'code' => 'MR-BATCH-02',
        'production_order_id' => $otherOrder->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $userId,
    ]);
    MaterialRequisitionItem::query()->create([
        'requisition_id' => $otherRequisition->id,
        'material_id' => $material->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);
    InventoryTransaction::query()->create([
        'inventory_id' => $inventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -5,
        'before_quantity' => 84,
        'after_quantity' => 79,
        'reference_no' => $otherRequisition->code,
        'remark' => '生产发料',
        'created_by_id' => $userId,
    ]);

    $unrelatedRequisition = MaterialRequisition::query()->create([
        'code' => 'MR-BATCH-03',
        'production_order_id' => $unrelatedOrder->id,
        'status' => MaterialRequisition::ISSUED,
        'created_by_id' => $userId,
    ]);
    MaterialRequisitionItem::query()->create([
        'requisition_id' => $unrelatedRequisition->id,
        'material_id' => $material->id,
        'requested_quantity' => 5,
        'issued_quantity' => 5,
    ]);
    InventoryTransaction::query()->create([
        'inventory_id' => $unrelatedInventory->id,
        'material_id' => $material->id,
        'business_type' => InventoryTransaction::OUTBOUND,
        'quantity' => -5,
        'before_quantity' => 100,
        'after_quantity' => 95,
        'reference_no' => $unrelatedRequisition->code,
        'remark' => '生产发料',
        'created_by_id' => $userId,
    ]);

    $batch = ProductionBatch::factory()->create([
        'code' => 'PB-BATCH-01',
        'production_order_id' => $order->id,
        'quantity' => 2,
    ]);
    ProductSn::factory()->create([
        'sn' => 'SN-BATCH-001',
        'production_order_id' => $order->id,
        'production_batch_id' => $batch->id,
        'material_batch_no' => null,
    ]);
    ProductSn::factory()->create([
        'sn' => 'SN-BATCH-002',
        'production_order_id' => $order->id,
        'production_batch_id' => $batch->id,
        'material_batch_no' => null,
        'status' => ProductSn::VOIDED,
        'voided_at' => now(),
        'void_reason' => '测试报废',
    ]);
    $emptyBatch = ProductionBatch::factory()->create([
        'code' => 'PB-BATCH-EMPTY',
        'production_order_id' => $order->id,
        'quantity' => 1,
    ]);
    $otherBatch = ProductionBatch::factory()->create([
        'code' => 'PB-BATCH-02',
        'production_order_id' => $otherOrder->id,
        'quantity' => 1,
    ]);
    ProductSn::factory()->create([
        'sn' => 'SN-BATCH-003',
        'production_order_id' => $otherOrder->id,
        'production_batch_id' => $otherBatch->id,
    ]);
    $unrelatedBatch = ProductionBatch::factory()->create([
        'code' => 'PB-BATCH-999',
        'production_order_id' => $unrelatedOrder->id,
        'quantity' => 1,
    ]);
    $unrelatedSn = ProductSn::factory()->create([
        'sn' => 'SN-BATCH-999',
        'production_order_id' => $unrelatedOrder->id,
        'production_batch_id' => $unrelatedBatch->id,
    ]);

    return compact(
        'material',
        'order',
        'otherOrder',
        'requisition',
        'otherRequisition',
        'batch',
        'emptyBatch',
        'otherBatch',
        'unrelatedSn',
    );
}

/**
 * @return array<int, string>
 */
function backwardTracePermissions(): array
{
    return ['traceability.backward.show'];
}

it('returns the product serial numbers grouped by production batch for a material batch', function () {
    $case = backwardTraceCase();
    $actor = userWithPermissions(backwardTracePermissions());

    $response = bearer($actor)
        ->getJson('/api/v1/traceability/material/LOT-BATCH-001')
        ->assertOk()
        ->assertJsonPath('data.batch_no', 'LOT-BATCH-001')
        ->assertJsonPath('data.has_data', true)
        ->assertJsonPath('data.message', null)
        ->assertJsonPath('data.data_granularity.linkage', 'material_requisition')
        ->assertJsonPath('data.data_granularity.production_batches', 'order')
        ->assertJsonPath('data.data_granularity.product_sns', 'sn')
        ->assertJsonPath('data.summary.material_count', 1)
        ->assertJsonPath('data.summary.production_order_count', 2)
        ->assertJsonPath('data.summary.production_batch_count', 2)
        ->assertJsonPath('data.summary.sn_count', 3)
        ->assertJsonPath('data.materials.0.code', 'MAT-BATCH-01')
        ->assertJsonCount(2, 'data.production_batches')
        ->assertJsonPath('data.production_batches.0.production_order.code', $case['order']->code)
        ->assertJsonPath('data.production_batches.0.material.code', 'MAT-BATCH-01')
        ->assertJsonPath('data.production_batches.0.code', 'PB-BATCH-01')
        ->assertJsonPath('data.production_batches.0.issued_quantity', '18.000')
        ->assertJsonCount(2, 'data.production_batches.0.product_sns')
        ->assertJsonPath('data.production_batches.0.product_sns.0.sn', 'SN-BATCH-001')
        ->assertJsonPath('data.production_batches.0.product_sns.0.material_batch_no', null)
        ->assertJsonPath('data.production_batches.0.product_sns.1.sn', 'SN-BATCH-002')
        ->assertJsonPath('data.production_batches.0.product_sns.1.status', ProductSn::VOIDED)
        ->assertJsonPath('data.production_batches.1.production_order.code', $case['otherOrder']->code)
        ->assertJsonPath('data.production_batches.1.code', 'PB-BATCH-02')
        ->assertJsonPath('data.production_batches.1.issued_quantity', '5.000')
        ->assertJsonCount(1, 'data.production_batches.1.product_sns')
        ->assertJsonPath('data.production_batches.1.product_sns.0.sn', 'SN-BATCH-003');

    expect($response->json('data.production_batches'))
        ->not->toContain(['code' => $case['emptyBatch']->code])
        ->and($response->json('data.production_batches.0.product_sns'))
        ->not->toContain(['sn' => $case['unrelatedSn']->sn]);
});

it('returns 200 with an empty result when the material batch has no products', function () {
    $actor = userWithPermissions(backwardTracePermissions());

    bearer($actor)
        ->getJson('/api/v1/traceability/material/UNKNOWN-BATCH')
        ->assertOk()
        ->assertJsonPath('data.batch_no', 'UNKNOWN-BATCH')
        ->assertJsonPath('data.has_data', false)
        ->assertJsonPath('data.message', '该物料批次未关联任何产品 SN')
        ->assertJsonPath('data.summary.sn_count', 0)
        ->assertJsonCount(0, 'data.production_batches');
});

it('rejects a material batch number longer than the inventory column', function () {
    $actor = userWithPermissions(backwardTracePermissions());

    bearer($actor)
        ->getJson('/api/v1/traceability/material/'.str_repeat('A', 81))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('batch_no', 'data.errors');
});

it('returns 403 without the backward trace permission', function () {
    $actor = userWithPermissions(['traceability.forward.show']);

    bearer($actor)
        ->getJson('/api/v1/traceability/material/LOT-BATCH-001')
        ->assertForbidden();
});

it('returns 401 when the caller is unauthenticated', function () {
    $this->getJson('/api/v1/traceability/material/LOT-BATCH-001')
        ->assertUnauthorized();
});
