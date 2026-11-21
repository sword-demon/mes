<?php

namespace App\Modules\Quality\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Quality\Model\DefectCode;
use App\Modules\Quality\Model\DefectiveProduct;
use App\Modules\Quality\Model\ReworkOrder;
use App\Modules\System\Model\User;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Facades\DB;

class DefectiveProductService
{
    public function __construct(
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

    public const array RELATIONS = [
        'productionOrder.product',
        'routeOperation.process',
        'productionBatch',
        'defectCode',
        'handledBy',
        'reworkOrders.createdBy',
        'reworkOrders.recheckedBy',
    ];

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function create(array $data): DefectiveProduct
    {
        return DB::transaction(function () use ($data): DefectiveProduct {
            $order = ProductionOrder::query()->findOrFail((int) $data['production_order_id']);
            $this->dataScope->ensure($order);
            $operation = RouteOperation::query()
                ->whereKey((int) $data['route_operation_id'])
                ->where('route_id', $order->route_id)
                ->first();

            if ($operation === null) {
                throw new ApiException('工序不属于该生产订单', 422);
            }

            $batch = ProductionBatch::query()
                ->whereKey((int) $data['production_batch_id'])
                ->where('production_order_id', $order->id)
                ->first();

            if ($batch === null) {
                throw new ApiException('批次不属于该生产订单', 422);
            }

            $quantity = number_format((float) $data['quantity'], 3, '.', '');
            $batchQuantity = number_format((float) $batch->quantity, 3, '.', '');

            if (bccomp($quantity, $batchQuantity, 3) > 0) {
                throw new ApiException('不良数量不能大于批次数量', 422);
            }

            $defectCode = DefectCode::query()
                ->whereKey((int) $data['defect_code_id'])
                ->where('status', true)
                ->first();

            if ($defectCode === null) {
                throw new ApiException('缺陷代码不存在或已停用', 422);
            }

            $defect = DefectiveProduct::query()->create([
                'production_order_id' => $order->id,
                'route_operation_id' => $operation->id,
                'production_batch_id' => $batch->id,
                'defect_code_id' => $defectCode->id,
                'quantity' => $quantity,
                'remark' => $data['remark'] ?? null,
                'status' => DefectiveProduct::PENDING,
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'quality.defect.create',
                actionName: '登记不良品',
                objectType: '不良品',
                objectId: $defect->id,
                objectName: $this->label($defect),
                after: [
                    '缺陷代码' => $defectCode->name,
                    '不良数量' => $quantity,
                ],
            ));

            return $defect->load(self::RELATIONS);
        });
    }

    /**
     * @throws ApiException
     */
    public function start(DefectiveProduct $defect, User $handler): DefectiveProduct
    {
        return DB::transaction(function () use ($defect, $handler): DefectiveProduct {
            $locked = $this->lock($defect);
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();
            $this->ensureStatus($locked, DefectiveProduct::PENDING, '开始处理');
            $locked->update([
                'status' => DefectiveProduct::PROCESSING,
                'handled_by_id' => $handler->id,
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'quality.defect.start',
                actionName: '开始处理不良品',
                objectType: '不良品',
                objectId: $locked->id,
                objectName: $this->label($locked),
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel()],
            ));

            return $locked->load(self::RELATIONS);
        });
    }

    /**
     * @throws ApiException
     */
    public function rework(DefectiveProduct $defect, string $reason, User $handler): DefectiveProduct
    {
        return DB::transaction(function () use ($defect, $reason, $handler): DefectiveProduct {
            $locked = $this->lock($defect);
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();
            $this->ensureStatus($locked, DefectiveProduct::PROCESSING, '返工');

            $sequence = $locked->reworkOrders()->count() + 1;
            $locked->reworkOrders()->create([
                'code' => "RW-{$locked->id}-{$sequence}",
                'reason' => $reason,
                'created_by_id' => $handler->id,
            ]);
            $locked->update([
                'status' => DefectiveProduct::PENDING_RECHECK,
                'handled_by_id' => $handler->id,
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'quality.defect.rework',
                actionName: '不良品返工',
                objectType: '不良品',
                objectId: $locked->id,
                objectName: $this->label($locked),
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel(), '返工原因' => $reason],
            ));

            return $locked->load(self::RELATIONS);
        });
    }

    /**
     * Scrapping is a terminal disposition, so it skips the recheck gate that
     * rework must clear; the whole defective quantity goes with it, otherwise
     * the remainder would silently disappear from the ledger.
     *
     * @throws ApiException
     */
    public function scrap(DefectiveProduct $defect, string $reason, string $quantity, User $handler): DefectiveProduct
    {
        return DB::transaction(function () use ($defect, $reason, $quantity, $handler): DefectiveProduct {
            $locked = $this->lock($defect);
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();

            $this->ensureStatus($locked, DefectiveProduct::PROCESSING, '报废');

            $scrapQuantity = number_format((float) $quantity, 3, '.', '');
            $defectQuantity = number_format((float) $locked->quantity, 3, '.', '');

            if (bccomp($scrapQuantity, $defectQuantity, 3) !== 0) {
                throw new ApiException('报废数量必须等于不良数量', 422);
            }

            $locked->update([
                'status' => DefectiveProduct::CLOSED,
                'handled_by_id' => $handler->id,
                'scrap_reason' => $reason,
                'scrap_quantity' => $scrapQuantity,
                'scrapped_at' => now(),
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'quality.defect.scrap',
                actionName: '不良品报废',
                objectType: '不良品',
                objectId: $locked->id,
                objectName: $this->label($locked),
                before: ['状态' => $before],
                after: [
                    '状态' => $locked->statusLabel(),
                    '报废数量' => $scrapQuantity,
                    '报废原因' => $reason,
                ],
            ));

            return $locked->load(self::RELATIONS);
        });
    }

    /**
     * Only a pending recheck can close a defect; a failed recheck sends it back
     * to processing so the next rework round starts from a known state.
     *
     * @throws ApiException
     */
    public function recheck(DefectiveProduct $defect, bool $passed, ?string $remark, User $inspector): DefectiveProduct
    {
        return DB::transaction(function () use ($defect, $passed, $remark, $inspector): DefectiveProduct {
            $locked = $this->lock($defect);
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();
            $this->ensureStatus($locked, DefectiveProduct::PENDING_RECHECK, '复检');
            $rework = $locked->reworkOrders()->reorder('id', 'desc')->firstOrFail();

            $rework->update([
                'recheck_result' => $passed ? ReworkOrder::PASSED : ReworkOrder::FAILED,
                'recheck_remark' => $remark,
                'rechecked_by_id' => $inspector->id,
                'rechecked_at' => now(),
            ]);
            $locked->update([
                'status' => $passed ? DefectiveProduct::CLOSED : DefectiveProduct::PROCESSING,
                'handled_by_id' => $inspector->id,
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'quality.defect.recheck',
                actionName: '不良品复检',
                objectType: '不良品',
                objectId: $locked->id,
                objectName: $this->label($locked),
                before: ['状态' => $before],
                after: [
                    '状态' => $locked->statusLabel(),
                    '复检结果' => $passed ? '合格' : '不合格',
                    '复检备注' => $remark,
                ],
            ));

            return $locked->load(self::RELATIONS);
        });
    }

    /**
     * Defects carry no own code; they are identified by their order and the
     * defect classification.
     */
    private function label(DefectiveProduct $defect): string
    {
        // Both foreign keys are required, so the relations always resolve.
        return trim($defect->productionOrder->code.' '.$defect->defectCode->name);
    }

    /**
     * @throws ApiException
     */
    private function lock(DefectiveProduct $defect): DefectiveProduct
    {
        return DefectiveProduct::query()->lockForUpdate()->whereKey($defect->getKey())->firstOrFail();
    }

    /**
     * @throws ApiException
     */
    private function ensureStatus(DefectiveProduct $defect, string $expected, string $action): void
    {
        if ($defect->status !== $expected) {
            throw new ApiException(
                "不良品当前状态为「{$defect->statusLabel()}」，不能{$action}",
                422,
            );
        }
    }
}
