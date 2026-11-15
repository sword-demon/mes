<?php

namespace App\Modules\Material\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Inventory\Service\InventoryService;
use App\Modules\Material\Model\MaterialRequisition;
use App\Modules\Material\Model\MaterialRequisitionItem;
use App\Modules\Material\Model\MaterialReturn;
use App\Modules\Material\Model\MaterialReturnItem;
use Illuminate\Support\Facades\DB;

class MaterialReturnService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function suggestions(MaterialRequisition $requisition): array
    {
        $items = $requisition->items()->with('material')->orderBy('id')->get();
        $returned = $this->returnedByItem($items->pluck('id')->all());

        return $items
            ->map(function (MaterialRequisitionItem $item) use ($returned): array {
                $issued = Quantity::toMilli($item->issued_quantity);
                $approvedQuantity = $returned['approved'][$item->id] ?? 0;
                $pendingQuantity = $returned['pending'][$item->id] ?? 0;

                return [
                    'requisition_item_id' => $item->id,
                    'material' => [
                        'id' => $item->material->id,
                        'code' => $item->material->code,
                        'name' => $item->material->name,
                        'unit' => $item->material->unit,
                        'batch_managed' => $item->material->batch_managed,
                    ],
                    'issued_quantity' => Quantity::toFloat($issued),
                    'approved_returned_quantity' => Quantity::toFloat($approvedQuantity),
                    'pending_quantity' => Quantity::toFloat($pendingQuantity),
                    'returnable_quantity' => Quantity::toFloat(max(0, $issued - $approvedQuantity - $pendingQuantity)),
                ];
            })
            ->filter(fn (array $item): bool => $item['returnable_quantity'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $userId): MaterialReturn
    {
        return DB::transaction(function () use ($data, $userId): MaterialReturn {
            $requisition = MaterialRequisition::query()->lockForUpdate()->findOrFail($data['requisition_id']);
            $items = $requisition->items()->lockForUpdate()->get()->keyBy('id');
            $returned = $this->returnedByItem($items->keys()->all());

            $return = MaterialReturn::query()->create([
                'code' => DocumentNumber::next('RT-', MaterialReturn::class),
                'requisition_id' => $requisition->id,
                'production_order_id' => $requisition->production_order_id,
                'remark' => $data['remark'] ?? null,
                'created_by_id' => $userId,
            ]);

            foreach ($data['items'] as $row) {
                $item = $items->get($row['requisition_item_id']);

                if (! $item instanceof MaterialRequisitionItem) {
                    throw new ApiException('退料明细不属于该领料单', 422);
                }

                $returnable = Quantity::toMilli($item->issued_quantity)
                    - ($returned['approved'][$item->id] ?? 0)
                    - ($returned['pending'][$item->id] ?? 0);

                if (Quantity::toMilli($row['quantity']) > $returnable) {
                    throw new ApiException('退料数量超过可退数量', 422);
                }

                $return->items()->create([
                    'requisition_item_id' => $item->id,
                    'quantity' => $row['quantity'],
                ]);
            }

            return $this->loadReturn($return);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function approve(MaterialReturn $materialReturn, array $data, int $userId): MaterialReturn
    {
        return DB::transaction(function () use ($materialReturn, $data, $userId): MaterialReturn {
            $materialReturn = MaterialReturn::query()->lockForUpdate()->findOrFail($materialReturn->id);

            if ($materialReturn->status !== MaterialReturn::PENDING) {
                throw new ApiException('退料单已审核', 422);
            }

            $items = $materialReturn->items()->with('requisitionItem')->lockForUpdate()->get()->keyBy('id');
            $requisitionItems = MaterialRequisitionItem::query()
                ->whereIn('id', $items->pluck('requisition_item_id'))
                ->lockForUpdate()
                ->get();
            $returned = $this->returnedByItem($items->pluck('requisition_item_id')->all());

            if (count($data['items']) !== $items->count()) {
                throw new ApiException('请为每条退料明细指定退回库位', 422);
            }

            foreach ($data['items'] as $row) {
                $item = $items->get($row['id']);

                if (! $item instanceof MaterialReturnItem) {
                    throw new ApiException('退料明细不属于该退料单', 422);
                }

                $requisitionItem = $requisitionItems->firstWhere('id', $item->requisition_item_id);
                $pendingOthers = ($returned['pending'][$requisitionItem->id] ?? 0) - Quantity::toMilli($item->quantity);
                $returnable = Quantity::toMilli($requisitionItem->issued_quantity)
                    - ($returned['approved'][$requisitionItem->id] ?? 0)
                    - max(0, $pendingOthers);

                if (Quantity::toMilli($item->quantity) > $returnable) {
                    throw new ApiException('退料数量超过可退数量', 422);
                }

                $this->inventory->inbound([
                    'material_id' => $requisitionItem->material_id,
                    'warehouse_id' => $row['warehouse_id'],
                    'location_id' => $row['location_id'],
                    'batch_no' => $row['batch_no'] ?? null,
                    'quantity' => $item->quantity,
                    'reference_no' => $materialReturn->code,
                    'remark' => '生产退料',
                ], $userId);
            }

            $materialReturn->update([
                'status' => MaterialReturn::APPROVED,
                'approved_by_id' => $userId,
                'approved_at' => now(),
            ]);

            return $this->loadReturn($materialReturn);
        });
    }

    /**
     * @throws ApiException
     */
    public function cancel(MaterialReturn $materialReturn, int $userId): MaterialReturn
    {
        return DB::transaction(function () use ($materialReturn, $userId): MaterialReturn {
            $materialReturn = MaterialReturn::query()->lockForUpdate()->findOrFail($materialReturn->id);

            if ($materialReturn->status !== MaterialReturn::PENDING) {
                throw new ApiException('退料单不是待审核状态，不能撤销', 422);
            }

            if ($materialReturn->created_by_id !== $userId) {
                throw new ApiException('只能撤销自己创建的退料单', 403, status: 403);
            }

            $materialReturn->update(['status' => MaterialReturn::CANCELLED]);

            return $this->loadReturn($materialReturn);
        });
    }

    /**
     * @param  array<int, int>  $requisitionItemIds
     * @return array{approved: array<int, int>, pending: array<int, int>}
     */
    private function returnedByItem(array $requisitionItemIds): array
    {
        $totals = ['approved' => [], 'pending' => []];

        if ($requisitionItemIds === []) {
            return $totals;
        }

        $items = MaterialReturnItem::query()
            ->with('materialReturn:id,status')
            ->whereIn('requisition_item_id', $requisitionItemIds)
            ->whereHas('materialReturn', fn ($query) => $query->whereIn('status', [
                MaterialReturn::APPROVED,
                MaterialReturn::PENDING,
            ]))
            ->get(['id', 'material_return_id', 'requisition_item_id', 'quantity']);

        foreach ($items as $item) {
            $bucket = $item->materialReturn->status === MaterialReturn::APPROVED ? 'approved' : 'pending';
            $totals[$bucket][$item->requisition_item_id] = ($totals[$bucket][$item->requisition_item_id] ?? 0)
                + Quantity::toMilli($item->quantity);
        }

        return $totals;
    }

    private function loadReturn(MaterialReturn $materialReturn): MaterialReturn
    {
        return $materialReturn->load(['productionOrder', 'requisition', 'items.requisitionItem.material', 'approvedBy']);
    }
}
