<?php

use App\Modules\Production\Model\ProductionOrder;

it('derives the allowed actions from the order status', function (string $status, array $actions) {
    $order = new ProductionOrder(['status' => $status]);

    expect($order->allowedActions())->toBe($actions);
})->with([
    'draft' => [ProductionOrder::DRAFT, ['submit', 'cancel']],
    'pending' => [ProductionOrder::PENDING, ['approve', 'cancel']],
    'approved' => [ProductionOrder::APPROVED, ['release', 'cancel']],
    'released' => [ProductionOrder::RELEASED, ['start', 'cancel']],
    'in progress' => [ProductionOrder::IN_PROGRESS, ['pause', 'complete', 'cancel']],
    'paused' => [ProductionOrder::PAUSED, ['resume', 'cancel']],
    'completed' => [ProductionOrder::COMPLETED, ['close']],
    'closed' => [ProductionOrder::CLOSED, []],
    'cancelled' => [ProductionOrder::CANCELLED, []],
]);

it('exposes the order-level facts for each status', function (
    string $status,
    bool $editable,
    bool $reportable,
    bool $generatable,
    bool $requisitionable,
    bool $splittable,
) {
    $order = new ProductionOrder(['status' => $status]);

    expect($order->isEditable())->toBe($editable)
        ->and($order->isReportable())->toBe($reportable)
        ->and($order->canGenerateSn())->toBe($generatable)
        ->and($order->isRequisitionable())->toBe($requisitionable)
        ->and($order->canSplit())->toBe($splittable);
})->with([
    'draft' => [ProductionOrder::DRAFT, true, false, false, false, false],
    'pending' => [ProductionOrder::PENDING, false, false, false, false, false],
    'approved' => [ProductionOrder::APPROVED, false, false, false, false, false],
    'released' => [ProductionOrder::RELEASED, false, true, true, true, true],
    'in progress' => [ProductionOrder::IN_PROGRESS, false, true, true, true, false],
    'paused' => [ProductionOrder::PAUSED, false, true, true, true, false],
    'completed' => [ProductionOrder::COMPLETED, false, false, false, false, false],
    'closed' => [ProductionOrder::CLOSED, false, false, false, false, false],
    'cancelled' => [ProductionOrder::CANCELLED, false, false, false, false, false],
]);
