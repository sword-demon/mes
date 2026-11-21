<?php

namespace App\Modules\Inventory\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Inventory\Model\Inventory;
use App\Modules\Inventory\Model\InventoryAlert;
use App\Modules\Material\Model\Material;
use App\Modules\System\Model\User;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryAlertService
{
    public function __construct(private readonly OperationLogService $operationLogs) {}

    /**
     * Reconcile low-stock alerts against material-wide stock totals.
     *
     * @return array{created: int, updated: int, resolved: int}
     */
    public function scan(): array
    {
        $created = 0;
        $updated = 0;
        $resolved = 0;

        Material::query()->chunkById(200, function ($materials) use (&$created, &$updated, &$resolved): void {
            foreach ($materials as $material) {
                DB::transaction(function () use ($material, &$created, &$updated, &$resolved): void {
                    $material = Material::query()->lockForUpdate()->find($material->id);

                    if ($material === null) {
                        return;
                    }

                    if (! $material->status) {
                        $resolved += InventoryAlert::query()
                            ->where('material_id', $material->id)
                            ->whereIn('status', [InventoryAlert::PENDING, InventoryAlert::CONFIRMED, InventoryAlert::CLOSED])
                            ->where(function ($query): void {
                                $query->whereIn('status', [InventoryAlert::PENDING, InventoryAlert::CONFIRMED])
                                    ->orWhereNull('resolved_at');
                            })
                            ->update([
                                'status' => InventoryAlert::CLOSED,
                                'active_key' => null,
                                'resolved_at' => now(),
                                'updated_at' => now(),
                            ]);

                        return;
                    }

                    $currentQuantity = number_format(
                        (float) Inventory::query()->where('material_id', $material->id)->sum('current_quantity'),
                        3,
                        '.',
                        '',
                    );
                    $minimumQuantity = number_format((float) $material->min_stock, 3, '.', '');
                    $belowMinimum = (int) round((float) $currentQuantity * 1000) < (int) round((float) $minimumQuantity * 1000)
                        && (int) round((float) $minimumQuantity * 1000) > 0;
                    $activeAlert = InventoryAlert::query()
                        ->where('material_id', $material->id)
                        ->whereIn('status', [InventoryAlert::PENDING, InventoryAlert::CONFIRMED])
                        ->first();

                    if (! $belowMinimum) {
                        if ($activeAlert !== null) {
                            $activeAlert->update(['status' => InventoryAlert::CLOSED, 'resolved_at' => now()]);
                            $resolved++;
                        }

                        $resolved += InventoryAlert::query()->where('material_id', $material->id)
                            ->where('status', InventoryAlert::CLOSED)
                            ->whereNull('resolved_at')
                            ->update(['resolved_at' => now(), 'active_key' => null]);

                        return;
                    }

                    if ($activeAlert !== null) {
                        if ($activeAlert->min_stock !== $minimumQuantity
                            || $activeAlert->current_quantity !== $currentQuantity) {
                            $activeAlert->update([
                                'min_stock' => $material->min_stock,
                                'current_quantity' => $currentQuantity,
                            ]);
                            $updated++;
                        }

                        return;
                    }

                    $inserted = InventoryAlert::query()->insertOrIgnore([[
                        'material_id' => $material->id,
                        'min_stock' => $material->min_stock,
                        'current_quantity' => $currentQuantity,
                        'status' => InventoryAlert::PENDING,
                        'active_key' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]]);
                    $created += $inserted;
                });
            }
        });

        return compact('created', 'updated', 'resolved');
    }

    public function confirm(InventoryAlert $alert, User $handler): InventoryAlert
    {
        return DB::transaction(function () use ($alert, $handler): InventoryAlert {
            Material::query()->lockForUpdate()->findOrFail($alert->material_id);
            $locked = InventoryAlert::query()->lockForUpdate()->findOrFail($alert->id);

            if ($locked->status !== InventoryAlert::PENDING) {
                throw new ApiException('只有待确认的库存预警可以确认', 422);
            }

            $before = $locked->statusLabel();
            $locked->update([
                'status' => InventoryAlert::CONFIRMED,
                'handled_by_id' => $handler->id,
                'handled_at' => now(),
            ]);

            $locked->load(['material', 'handledBy']);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'inventory.alert.confirm',
                actionName: '确认库存预警',
                objectType: '库存预警',
                objectId: $locked->id,
                objectName: $locked->material->code,
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel()],
            ));

            return $locked;
        });
    }

    public function close(InventoryAlert $alert, User $handler): InventoryAlert
    {
        return DB::transaction(function () use ($alert, $handler): InventoryAlert {
            $material = Material::query()->lockForUpdate()->findOrFail($alert->material_id);
            $locked = InventoryAlert::query()->lockForUpdate()->findOrFail($alert->id);

            if (! in_array($locked->status, [InventoryAlert::PENDING, InventoryAlert::CONFIRMED], true)) {
                throw new ApiException('库存预警已关闭', 422);
            }

            $currentQuantity = number_format(
                (float) Inventory::query()->where('material_id', $material->id)->sum('current_quantity'),
                3,
                '.',
                '',
            );
            $minimumQuantity = number_format((float) $material->min_stock, 3, '.', '');

            $before = $locked->statusLabel();
            $locked->update([
                'status' => InventoryAlert::CLOSED,
                'min_stock' => $minimumQuantity,
                'current_quantity' => $currentQuantity,
                'handled_by_id' => $handler->id,
                'handled_at' => now(),
                'resolved_at' => (int) round((float) $currentQuantity * 1000)
                    >= (int) round((float) $minimumQuantity * 1000)
                    ? now()
                    : null,
            ]);

            $locked->load(['material', 'handledBy']);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'inventory.alert.close',
                actionName: '关闭库存预警',
                objectType: '库存预警',
                objectId: $locked->id,
                objectName: $locked->material->code,
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel()],
            ));

            return $locked;
        });
    }

    /** @return Builder<InventoryAlert> */
    public function activeQuery(): Builder
    {
        return InventoryAlert::query()
            ->with(['material', 'handledBy'])
            ->whereIn('status', [InventoryAlert::PENDING, InventoryAlert::CONFIRMED]);
    }
}
