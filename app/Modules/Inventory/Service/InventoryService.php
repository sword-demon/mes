<?php

namespace App\Modules\Inventory\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryTransaction;
use App\Modules\Inventory\Model\StorageLocation;
use App\Modules\Material\Model\Material;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private readonly OperationLogService $operationLogs) {}

    private const int MAX_MILLI = 999_999_999_999;

    /**
     * Expected keys: material_id, warehouse_id, location_id, batch_no, quantity,
     * reference_no, remark.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function inbound(array $data, ?int $operatorId = null): Inventory
    {
        return $this->apply($data, InventoryTransaction::INBOUND, $operatorId);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function outbound(array $data, ?int $operatorId = null): Inventory
    {
        return $this->apply($data, InventoryTransaction::OUTBOUND, $operatorId);
    }

    /**
     * Expected keys: material_id, warehouse_id, location_id, batch_no,
     * current_quantity, reference_no, remark.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function adjust(array $data, ?int $operatorId = null): Inventory
    {
        return $this->apply($data, InventoryTransaction::ADJUSTMENT, $operatorId);
    }

    /**
     * The unique position key plus firstOrCreate's retry absorbs the first-insert
     * race; the row lock then serialises movements on an existing balance, so
     * the available-stock check and the write see the same quantity.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    private function apply(array $data, string $businessType, ?int $operatorId): Inventory
    {
        return DB::transaction(function () use ($data, $businessType, $operatorId): Inventory {
            $material = Material::query()->lockForUpdate()->whereKey($data['material_id'])->first();

            if ($material === null || ! $material->status) {
                throw new ApiException('物料不存在或已停用', 422);
            }

            $location = StorageLocation::query()->with('warehouse')->whereKey($data['location_id'])->first();

            if ($location === null
                || $location->warehouse_id !== (int) $data['warehouse_id']
                || ! $location->status
                || $location->warehouse === null
                || ! $location->warehouse->status) {
                throw new ApiException('库位不存在、已停用或不属于所选仓库', 422);
            }

            $batchNo = trim((string) ($data['batch_no'] ?? ''));

            if ($material->batch_managed && $batchNo === '') {
                throw new ApiException('批次管理物料必须填写批次号', 422);
            }

            $inventory = Inventory::query()->firstOrCreate(
                [
                    'material_id' => $material->id,
                    'location_id' => $location->id,
                    'batch_no' => $batchNo,
                ],
                ['current_quantity' => 0, 'frozen_quantity' => 0],
            );

            $inventory = Inventory::query()->lockForUpdate()->findOrFail($inventory->id);

            $before = $this->milli($inventory->current_quantity);
            $frozen = $this->milli($inventory->frozen_quantity);

            $after = match ($businessType) {
                InventoryTransaction::INBOUND => $before + $this->milli($data['quantity'] ?? 0),
                InventoryTransaction::OUTBOUND => $this->outboundBalance($before, $frozen, $this->milli($data['quantity'] ?? 0)),
                InventoryTransaction::ADJUSTMENT => $this->adjustedBalance($frozen, $data['current_quantity'] ?? 0),
                default => throw new ApiException('不支持的库存变动类型', 422),
            };

            if ($after > self::MAX_MILLI) {
                throw new ApiException('库存数量超过上限', 422);
            }

            if ($businessType === InventoryTransaction::ADJUSTMENT && $after === $before) {
                throw new ApiException('调整后库存与当前库存一致', 422);
            }

            $inventory->update(['current_quantity' => $this->decimal($after)]);

            InventoryTransaction::query()->create([
                'inventory_id' => $inventory->id,
                'material_id' => $material->id,
                'business_type' => $businessType,
                'quantity' => $this->decimal($after - $before),
                'before_quantity' => $this->decimal($before),
                'after_quantity' => $this->decimal($after),
                'reference_no' => $data['reference_no'] ?? null,
                'remark' => $data['remark'] ?? null,
                'created_by_id' => $operatorId,
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'inventory.'.$businessType,
                actionName: match ($businessType) {
                    InventoryTransaction::INBOUND => '库存入库',
                    InventoryTransaction::OUTBOUND => '库存出库',
                    default => '库存调整',
                },
                objectType: '库存',
                objectId: $inventory->id,
                objectName: $material->code.' '.$material->name.' / '.$location->code.($batchNo !== '' ? ' / 批次 '.$batchNo : ''),
                before: ['当前库存' => $this->decimal($before)],
                after: ['当前库存' => $this->decimal($after)],
            ));

            return $inventory;
        });
    }

    /**
     * @throws ApiException
     */
    private function outboundBalance(int $before, int $frozen, int $quantity): int
    {
        if ($quantity > $before - $frozen) {
            throw new ApiException('出库数量超过可用库存', 422);
        }

        return $before - $quantity;
    }

    /**
     * @throws ApiException
     */
    private function adjustedBalance(int $frozen, mixed $target): int
    {
        $after = $this->milli($target);

        if ($after < $frozen) {
            throw new ApiException('库存调整不能低于冻结数量', 422);
        }

        return $after;
    }

    private function milli(mixed $value): int
    {
        return (int) round((float) $value * 1000);
    }

    private function decimal(int $milli): string
    {
        return number_format($milli / 1000, 3, '.', '');
    }
}
