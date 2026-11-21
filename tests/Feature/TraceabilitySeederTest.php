<?php

use App\Core\DataScope\DataScope;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Production\Model\ProductSn;
use App\Modules\Traceability\Service\BackwardTraceService;
use App\Modules\Traceability\Service\ForwardTraceService;

it('returns the forward trace with process and material history for a seeded SN', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    $sn = ProductSn::query()->whereHas('processRecords')->orderBy('id')->first();

    expect($sn)->not->toBeNull()
        ->and(MaterialRequisition::query()->count())->toBeGreaterThanOrEqual(16);

    $forward = app(ForwardTraceService::class)->trace($sn);
    $issuedMaterials = collect($forward->materials)
        ->filter(fn ($usage): bool => (float) $usage->issuedQuantity > 0);

    expect($forward->processRecords->count())->toBeGreaterThan(1)
        ->and($issuedMaterials)->not->toBeEmpty();
});

it('returns the backward trace with affected SNs for a seeded material batch', function () {
    $this->travelTo('2026-11-17 10:00:00');

    $this->seed();

    $outbound = InventoryTransaction::query()
        ->where('business_type', InventoryTransaction::OUTBOUND)
        ->whereNotNull('reference_no')
        ->orderBy('id')
        ->first();

    expect($outbound)->not->toBeNull();

    $backward = app(BackwardTraceService::class)->trace(
        (string) $outbound->inventory->batch_no,
        DataScope::all(),
    );

    expect($backward->rows)->not->toBeEmpty()
        ->and($backward->rows->flatMap(fn ($row) => $row->productSns))->not->toBeEmpty();
});
