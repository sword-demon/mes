<?php

namespace App\Modules\Material\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Bom\Model\BomItem;
use App\Modules\Inventory\Service\InventoryService;
use App\Modules\Material\Model\Material;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Production\Model\ProductionOrder;
use Illuminate\Support\Facades\DB;

class MaterialRequisitionService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): MaterialRequisition
    {
        return DB::transaction(function () use ($data, $userId): MaterialRequisition {
            $order = ProductionOrder::query()->whereKey($data['production_order_id'])->firstOrFail();
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

            return $requisition->load('items');
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

            if ($requisition->status !== MaterialRequisition::PENDING) {
                throw new ApiException('领料单已发料', 422);
            }

            $items = $requisition->items()->lockForUpdate()->get()->keyBy('id');

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

            return $this->markOverRequirement($requisition);
        });
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
        if (in_array($order->status, [
            ProductionOrder::RELEASED,
            ProductionOrder::IN_PROGRESS,
            ProductionOrder::PAUSED,
        ], true)) {
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
