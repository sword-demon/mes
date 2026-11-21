<?php

namespace App\Modules\Material\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Bom\Model\BomItem;
use App\Modules\Inventory\Service\InventoryService;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaterialRequisitionService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): MaterialRequisition
    {
        return DB::transaction(function () use ($data, $userId): MaterialRequisition {
            $order = ProductionOrder::query()->whereKey($data['production_order_id'])->firstOrFail();
            $this->dataScope->ensure($order);
            $this->ensureOrderCanRequisition($order);

            $requisition = MaterialRequisition::query()->create([
                'code' => DocumentNumber::next('MR-', MaterialRequisition::class),
                'production_order_id' => $order->id,
                'remark' => $data['remark'] ?? null,
                'created_by_id' => $userId,
            ]);

            foreach ($data['items'] as $row) {
                $material = Material::query()->whereKey($row['material_id'])->where('status', true)->first();

                if ($material === null) {
                    throw new ApiException('物料不存在或已停用', 422);
                }

                $requisition->items()->create([
                    'material_id' => $material->id,
                    'requested_quantity' => $row['requested_quantity'],
                ]);
            }

            $this->operationLogs->record(new OperationLogEntry(
                action: 'material.requisition.create',
                actionName: '创建领料单',
                objectType: '领料单',
                objectId: $requisition->id,
                objectName: $requisition->code,
                after: ['所属订单' => $order->code, '物料明细' => $this->itemSummary($requisition)],
            ));

            return $requisition->load('items.material');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function issue(MaterialRequisition $requisition, array $data, int $userId): MaterialRequisition
    {
        return DB::transaction(function () use ($requisition, $data, $userId): MaterialRequisition {
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            $this->dataScope->ensure($requisition);

            if ($requisition->status !== MaterialRequisition::PENDING) {
                throw new ApiException('领料单已发料', 422);
            }

            $items = $requisition->items()->with('material')->lockForUpdate()->get()->keyBy('id');

            foreach ($data['items'] as $row) {
                $item = $items->get($row['id']);

                if (! $item instanceof MaterialRequisitionItem) {
                    throw new ApiException('发料明细不属于该领料单', 422);
                }

                $remaining = Quantity::toMilli($item->requested_quantity) - Quantity::toMilli($item->issued_quantity);
                $quantity = Quantity::toMilli($row['quantity']);

                if ($quantity > $remaining) {
                    throw new ApiException('发料数量超过申请数量', 422);
                }

                $this->inventory->outbound([
                    'material_id' => $item->material_id,
                    'warehouse_id' => $row['warehouse_id'],
                    'location_id' => $row['location_id'],
                    'batch_no' => $row['batch_no'] ?? null,
                    'quantity' => $row['quantity'],
                    'reference_no' => $requisition->code,
                    'remark' => '生产发料',
                ], $userId);

                $item->update(['issued_quantity' => Quantity::toDecimal(Quantity::toMilli($item->issued_quantity) + $quantity)]);
            }

            $complete = $items->fresh()->every(
                fn (MaterialRequisitionItem $item): bool => Quantity::toMilli($item->issued_quantity) === Quantity::toMilli($item->requested_quantity),
            );
            $requisition->update(['status' => $complete ? MaterialRequisition::ISSUED : MaterialRequisition::PENDING]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'material.requisition.issue',
                actionName: '领料发料',
                objectType: '领料单',
                objectId: $requisition->id,
                objectName: $requisition->code,
                after: [
                    '发料明细' => $this->issueSummary($data['items'], $items),
                    '发料状态' => $complete ? '全部发完' : '部分发料',
                ],
            ));

            return $this->markOverRequirement($requisition);
        });
    }

    /**
     * Material codes with requested quantities, as one line per movement —
     * a multi-row requisition logs once, not per row.
     */
    private function itemSummary(MaterialRequisition $requisition): string
    {
        return MaterialSummary::format($requisition->items->map(
            fn (MaterialRequisitionItem $item): array => [$item->material->code, $item->requested_quantity],
        ));
    }

    /**
     * One summary line per issued row; a multi-row issue logs once.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  Collection<int, MaterialRequisitionItem>  $items
     */
    private function issueSummary(array $rows, Collection $items): string
    {
        $summary = [];

        foreach ($rows as $row) {
            $item = $items->get($row['id']);

            if ($item === null) {
                continue;
            }

            $summary[] = [$item->material->code, $row['quantity']];
        }

        return MaterialSummary::format($summary);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function suggestions(ProductionOrder $order): array
    {
        $issued = $this->issuedByMaterial($order);

        return BomItem::query()
            ->with('material')
            ->where('bom_id', $order->bom_id)
            ->orderBy('id')
            ->get()
            ->map(function (BomItem $item) use ($order, $issued): array {
                $required = $this->requiredQuantity($order, $item);
                $issuedQuantity = $issued[$item->material_id] ?? 0;

                return [
                    'material' => [
                        'id' => $item->material->id,
                        'code' => $item->material->code,
                        'name' => $item->material->name,
                        'unit' => $item->material->unit,
                    ],
                    'required_quantity' => Quantity::toFloat($required),
                    'issued_quantity' => Quantity::toFloat($issuedQuantity),
                    'suggested_quantity' => Quantity::toFloat(max(0, $required - $issuedQuantity)),
                ];
            })
            ->all();
    }

    private function markOverRequirement(MaterialRequisition $requisition): MaterialRequisition
    {
        $requisition->load(['items', 'productionOrder']);
        $issued = $this->issuedByMaterial($requisition->productionOrder);
        $required = $this->requiredByMaterial($requisition->productionOrder);

        $requisition->items->each(function (MaterialRequisitionItem $item) use ($issued, $required): void {
            $item->update([
                'over_requirement' => ($issued[$item->material_id] ?? 0) > ($required[$item->material_id] ?? 0),
            ]);
        });

        return $requisition;
    }

    /**
     * @return array<int, int>
     */
    private function issuedByMaterial(ProductionOrder $order): array
    {
        return MaterialRequisitionItem::query()
            ->whereHas('requisition', fn ($query) => $query->where('production_order_id', $order->id))
            ->get(['material_id', 'issued_quantity'])
            ->groupBy('material_id')
            ->map(fn ($items) => $items->sum(fn (MaterialRequisitionItem $item): int => Quantity::toMilli($item->issued_quantity)))
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function requiredByMaterial(ProductionOrder $order): array
    {
        return BomItem::query()
            ->where('bom_id', $order->bom_id)
            ->get()
            ->mapWithKeys(fn (BomItem $item): array => [$item->material_id => $this->requiredQuantity($order, $item)])
            ->all();
    }

    /**
     * @throws ApiException
     */
    private function ensureOrderCanRequisition(ProductionOrder $order): void
    {
        if ($order->isRequisitionable()) {
            return;
        }

        throw new ApiException("生产订单当前状态为「{$order->statusLabel()}」，不能领料", 422);
    }

    private function requiredQuantity(ProductionOrder $order, BomItem $item): int
    {
        return Quantity::toMilli(
            (float) $order->planned_quantity * $item->quantity * (1 + $item->loss_rate / 100),
        );
    }
}
