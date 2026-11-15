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
        $returned = $this->approvedReturnedByItem($items->pluck('id')->all());

        return $items
            ->map(function (MaterialRequisitionItem $item) use ($returned): array {
                $issued = $this->milli($item->issued_quantity);
                $returnedQuantity = $returned[$item->id] ?? 0;

                return [
                    'requisition_item_id' => $item->id,
                    'material' => [
                        'id' => $item->material->id,
                        'code' => $item->material->code,
                        'name' => $item->material->name,
                        'unit' => $item->material->unit,
                        'batch_managed' => $item->material->batch_managed,
                    ],
                    'issued_quantity' => $this->quantity($issued),
                    'returned_quantity' => $this->quantity($returnedQuantity),
                    'returnable_quantity' => $this->quantity(max(0, $issued - $returnedQuantity)),
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
            $returned = $this->approvedReturnedByItem($items->keys()->all());

            $return = MaterialReturn::query()->create([
                'code' => $this->nextCode(),
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

                $returnable = $this->milli($item->issued_quantity) - ($returned[$item->id] ?? 0);

                if ($this->milli($row['quantity']) > $returnable) {
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
            $returned = $this->approvedReturnedByItem($items->pluck('requisition_item_id')->all(), $materialReturn->id);

            if (count($data['items']) !== $items->count()) {
                throw new ApiException('请为每条退料明细指定退回库位', 422);
            }

            foreach ($data['items'] as $row) {
                $item = $items->get($row['id']);

                if (! $item instanceof MaterialReturnItem) {
                    throw new ApiException('退料明细不属于该退料单', 422);
                }

                $requisitionItem = $requisitionItems->firstWhere('id', $item->requisition_item_id);
                $returnable = $this->milli($requisitionItem->issued_quantity) - ($returned[$requisitionItem->id] ?? 0);

                if ($this->milli($item->quantity) > $returnable) {
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
     * @param  array<int, int>  $requisitionItemIds
     * @return array<int, int>
     */
    private function approvedReturnedByItem(array $requisitionItemIds, ?int $exceptReturnId = null): array
    {
        if ($requisitionItemIds === []) {
            return [];
        }

        return MaterialReturnItem::query()
            ->whereIn('requisition_item_id', $requisitionItemIds)
            ->whereHas('materialReturn', fn ($query) => $query->where('status', MaterialReturn::APPROVED))
            ->when($exceptReturnId !== null, fn ($query) => $query->where('material_return_id', '!=', $exceptReturnId))
            ->get(['requisition_item_id', 'quantity'])
            ->groupBy('requisition_item_id')
            ->map(fn ($items) => $items->sum(fn (MaterialReturnItem $item): int => $this->milli($item->quantity)))
            ->all();
    }

    private function loadReturn(MaterialReturn $materialReturn): MaterialReturn
    {
        return $materialReturn->load(['productionOrder', 'requisition', 'items.requisitionItem.material', 'approvedBy']);
    }

    private function nextCode(): string
    {
        do {
            $code = 'RT-'.now()->format('YmdHis').'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
        } while (MaterialReturn::query()->where('code', $code)->exists());

        return $code;
    }

    private function quantity(int $milli): float
    {
        return (float) $this->decimal($milli);
    }

    private function milli(mixed $value): int
    {
        $normalized = number_format((float) $value, 3, '.', '');

        return (int) bcmul($normalized, '1000', 0);
    }

    private function decimal(int $milli): string
    {
        return number_format($milli / 1000, 3, '.', '');
    }
}
