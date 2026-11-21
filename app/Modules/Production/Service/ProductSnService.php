<?php

namespace App\Modules\Production\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Core\Services\BusinessDay;
use App\Modules\Equipment\Service\EquipmentService;
use App\Modules\Organization\Model\Employee;
use App\Modules\Production\Model\ProductionBatch;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Model\ProductSn;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ProductSnService
{
    public function __construct(
        private readonly EquipmentService $equipments,
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * Serial numbers are unique per day, so two concurrent batches can compute
     * the same next number; the unique index rejects one and the retry takes
     * the next slot instead of leaking a duplicate.
     *
     * @param  array<string, mixed>  $data
     * @return array{batch: ProductionBatch, sns: Collection<int, ProductSn>}
     *
     * @throws ApiException
     */
    public function generate(array $data): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): array => $this->createBatch($data));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function bind(ProductSn $sn, array $data): ProductSn
    {
        return DB::transaction(function () use ($sn, $data): ProductSn {
            $locked = ProductSn::query()->lockForUpdate()->whereKey($sn->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);
            $this->ensureActive($locked, '绑定');

            $materialBatchNo = $data['material_batch_no'] ?? null;
            $equipmentId = $data['equipment_id'] ?? null;
            $employeeId = $data['employee_id'] ?? null;

            if ($materialBatchNo === null && $equipmentId === null && $employeeId === null) {
                throw new ApiException('请至少绑定物料批次、设备或操作人员中的一项', 422);
            }

            if ($materialBatchNo !== null && $locked->material_batch_no !== null) {
                throw new ApiException('SN 已绑定物料批次，不能重复绑定', 422);
            }

            foreach (['equipment_id' => $equipmentId, 'employee_id' => $employeeId] as $field => $value) {
                if ($value !== null && (string) $locked->{$field} === (string) $value) {
                    throw new ApiException('SN 已绑定相同内容，不能重复绑定', 422);
                }
            }

            if ($employeeId !== null) {
                $this->ensureActiveEmployee((int) $employeeId);
            }

            if ($equipmentId !== null) {
                $this->equipments->available((int) $equipmentId);
            }

            $locked->update(array_filter([
                'material_batch_no' => $materialBatchNo,
                'equipment_id' => $equipmentId,
                'employee_id' => $employeeId,
            ], static fn (mixed $value): bool => $value !== null));

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.sn.bind',
                actionName: '绑定产品 SN',
                objectType: '产品 SN',
                objectId: $locked->id,
                objectName: $locked->sn,
                after: array_filter([
                    '物料批次' => $materialBatchNo,
                    '设备' => $equipmentId !== null ? (string) $equipmentId : null,
                    '操作人员' => $employeeId !== null ? (string) $employeeId : null,
                ], static fn (mixed $value): bool => $value !== null),
            ));

            return $locked;
        });
    }

    /**
     * @throws ApiException
     */
    public function print(ProductSn $sn): ProductSn
    {
        return DB::transaction(function () use ($sn): ProductSn {
            $locked = ProductSn::query()->lockForUpdate()->whereKey($sn->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);
            $this->ensureActive($locked, '打印标签');
            $locked->update(['printed_at' => now()]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.sn.print',
                actionName: '打印产品 SN',
                objectType: '产品 SN',
                objectId: $locked->id,
                objectName: $locked->sn,
            ));

            return $locked;
        });
    }

    /**
     * Read the label without claiming a print, so the page can render and then
     * report the actual print after the user confirms the dialog.
     *
     * @throws ApiException
     */
    public function prepareLabel(ProductSn $sn): ProductSn
    {
        $this->dataScope->ensure($sn);
        $this->ensureActive($sn, '打印标签');

        return $sn->load(['productionOrder.product', 'productionBatch']);
    }

    /**
     * @throws ApiException
     */
    public function void(ProductSn $sn, string $reason): ProductSn
    {
        return DB::transaction(function () use ($sn, $reason): ProductSn {
            $locked = ProductSn::query()->lockForUpdate()->whereKey($sn->getKey())->firstOrFail();
            $this->dataScope->ensure($locked);

            if ($locked->isVoided()) {
                throw new ApiException('SN 已作废，不能重复作废', 422);
            }

            $locked->update([
                'status' => ProductSn::VOIDED,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $this->operationLogs->record(new OperationLogEntry(
                action: 'production.sn.void',
                actionName: '作废产品 SN',
                objectType: '产品 SN',
                objectId: $locked->id,
                objectName: $locked->sn,
                after: ['作废原因' => $reason],
            ));

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{batch: ProductionBatch, sns: Collection<int, ProductSn>}
     *
     * @throws ApiException
     */
    private function createBatch(array $data): array
    {
        $order = ProductionOrder::query()->lockForUpdate()->whereKey($data['production_order_id'])->firstOrFail();
        $this->dataScope->ensure($order);
        $quantity = (int) $data['quantity'];

        if (! $order->canGenerateSn()) {
            $message = match ($order->status) {
                ProductionOrder::DRAFT => '生产订单尚未审核下达，不能生成 SN',
                ProductionOrder::CANCELLED, ProductionOrder::CLOSED => '已取消或已关闭的生产订单不能生成 SN',
                default => '生产订单尚未下达或已完成，不能生成 SN',
            };

            throw new ApiException($message, 422);
        }

        // Scrapped units free their plan slot so a replacement can be issued.
        $issued = ProductSn::query()
            ->where('production_order_id', $order->id)
            ->where('status', ProductSn::ACTIVE)
            ->count();

        if ($issued + $quantity > (int) round((float) $order->planned_quantity)) {
            throw new ApiException('SN 累计数量不能超过订单计划数量', 422);
        }

        $employeeId = $data['employee_id'] ?? null;

        if ($employeeId !== null) {
            $this->ensureActiveEmployee((int) $employeeId);
        }

        if (($data['equipment_id'] ?? null) !== null) {
            $this->equipments->available((int) $data['equipment_id']);
        }

        $batch = ProductionBatch::query()->create([
            'code' => $this->nextBatchCode($order),
            'production_order_id' => $order->id,
            'quantity' => $quantity,
        ]);

        $sns = new Collection;
        $day = now(BusinessDay::timezone());
        $sequence = $this->nextSequence($day);

        for ($index = 0; $index < $quantity; $index++) {
            $sns->push(ProductSn::query()->create([
                'sn' => $this->formatSn($day, $sequence++),
                'production_order_id' => $order->id,
                'production_batch_id' => $batch->id,
                'equipment_id' => $data['equipment_id'] ?? null,
                'employee_id' => $employeeId,
            ]));
        }

        $this->operationLogs->record(new OperationLogEntry(
            action: 'production.sn.generate',
            actionName: '生成产品 SN',
            objectType: '产品 SN 批次',
            objectId: $batch->id,
            objectName: $batch->code,
            after: ['所属订单' => $order->code, '数量' => $quantity],
        ));

        return ['batch' => $batch, 'sns' => $sns];
    }

    private function nextBatchCode(ProductionOrder $order): string
    {
        $sequence = ProductionBatch::query()->where('production_order_id', $order->id)->count() + 1;

        return $order->code.'-L'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Daily sequence in the PRD format: MES + Ymd + six digits.
     */
    private function nextSequence(\DateTimeInterface $day): int
    {
        $prefix = 'MES'.$day->format('Ymd');
        $latest = ProductSn::query()
            ->where('sn', 'like', $prefix.'%')
            ->orderByDesc('sn')
            ->value('sn');

        return $latest === null ? 1 : ((int) substr((string) $latest, strlen($prefix))) + 1;
    }

    /**
     * @throws ApiException
     */
    private function formatSn(\DateTimeInterface $day, int $sequence): string
    {
        if ($sequence > 999999) {
            throw new ApiException('当日 SN 序号已用尽', 422);
        }

        return 'MES'.$day->format('Ymd').str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function ensureActiveEmployee(int $employeeId): void
    {
        $exists = Employee::query()
            ->whereKey($employeeId)
            ->where('status', true)
            ->exists();

        if (! $exists) {
            throw new ApiException('操作人员不存在或已停用', 422);
        }
    }

    /**
     * @throws ApiException
     */
    private function ensureActive(ProductSn $sn, string $action): void
    {
        if ($sn->isVoided()) {
            throw new ApiException("SN 已作废，不能{$action}", 422);
        }
    }
}
