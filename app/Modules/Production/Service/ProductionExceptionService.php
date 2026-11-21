<?php

namespace App\Modules\Production\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionException;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\System\Model\User;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductionExceptionService
{
    public function __construct(
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

    /**
     * @param  array{type: string, production_order_id?: int|null, process_record_id?: int|null, description: string}  $data
     * @param  array<int, UploadedFile>  $files
     *
     * @throws ApiException
     */
    public function report(array $data, User $reporter, array $files = []): ProductionException
    {
        $paths = [];

        try {
            return DB::transaction(function () use ($data, $reporter, $files, &$paths): ProductionException {
                $record = isset($data['process_record_id'])
                    ? ProcessRecord::query()
                        ->with('productionTask')
                        ->whereKey($data['process_record_id'])
                        ->firstOrFail()
                    : null;
                $orderId = isset($data['production_order_id']) ? (int) $data['production_order_id'] : null;

                if ($orderId === null && $record === null) {
                    throw new ApiException('请关联生产订单或工序执行记录', 422);
                }

                if ($orderId !== null
                    && $record !== null
                    && (int) $record->productionTask->production_order_id !== $orderId) {
                    throw new ApiException('工序执行记录不属于该生产订单', 422);
                }

                // The exception is anchored on the order (the execution only
                // fills the order in), so the order's line decides visibility;
                // checking either handle guards the scope of the exception.
                if ($orderId !== null) {
                    $this->dataScope->ensure(
                        ProductionOrder::query()->lockForUpdate()->findOrFail($orderId),
                    );
                } elseif ($record !== null) {
                    $this->dataScope->ensure($record);
                }

                $orderId ??= (int) $record->productionTask->production_order_id;

                $exception = ProductionException::query()->create([
                    'code' => 'EXC-'.Str::ulid(),
                    'type' => $data['type'],
                    'status' => ProductionException::REPORTED,
                    'production_order_id' => $orderId,
                    'process_record_id' => $record?->id,
                    'description' => $data['description'],
                    'reported_by_id' => $reporter->id,
                    'reported_at' => now(),
                ]);

                foreach ($files as $file) {
                    $path = $file->store("production-exception-images/{$exception->id}", 'public');

                    if ($path === false) {
                        throw new ApiException('异常图片保存失败', 500);
                    }

                    $paths[] = $path;

                    $exception->images()->create([
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                    ]);
                }

                $this->operationLogs->record(new OperationLogEntry(
                    action: 'production.exception.report',
                    actionName: '上报生产异常',
                    objectType: '生产异常',
                    objectId: $exception->id,
                    objectName: $exception->code,
                    after: [
                        '异常类型' => $exception->typeLabel(),
                        '描述' => Str::limit($data['description'], 60),
                    ],
                ));

                return $this->load($exception);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($paths);

            throw $exception;
        }
    }

    public function process(ProductionException $exception, User $handler, string $result): ProductionException
    {
        if (trim($result) === '') {
            throw new ApiException('请填写处理结果', 422);
        }

        return $this->transition(
            $exception,
            ProductionException::REPORTED,
            '只有已上报的生产异常可以处理',
            fn (): array => [
                'status' => ProductionException::PROCESSING,
                'handled_by_id' => $handler->id,
                'handled_at' => now(),
                'handling_result' => $result,
            ],
            actionName: '处理生产异常',
            action: 'production.exception.process',
            after: fn (): array => ['处理结果' => $result],
        );
    }

    public function close(ProductionException $exception): ProductionException
    {
        return $this->transition(
            $exception,
            ProductionException::PROCESSING,
            '只有处理中的生产异常可以关闭',
            function (ProductionException $locked): array {
                if ($locked->handling_result === null || trim($locked->handling_result) === '') {
                    throw new ApiException('关闭生产异常前必须填写处理结果', 422);
                }

                return [
                    'status' => ProductionException::CLOSED,
                    'closed_at' => now(),
                ];
            },
            actionName: '关闭生产异常',
            action: 'production.exception.close',
        );
    }

    /**
     * @param  Closure(ProductionException): array<string, mixed>  $changes
     * @param  Closure(ProductionException): array<string, mixed>|null  $after
     */
    private function transition(
        ProductionException $exception,
        string $requiredStatus,
        string $error,
        Closure $changes,
        string $action,
        string $actionName,
        ?Closure $after = null,
    ): ProductionException {
        return DB::transaction(function () use ($exception, $requiredStatus, $error, $changes, $action, $actionName, $after): ProductionException {
            $locked = ProductionException::query()->lockForUpdate()->findOrFail($exception->id);
            $this->dataScope->ensure($locked);
            $before = $locked->statusLabel();

            if ($locked->status !== $requiredStatus) {
                throw new ApiException($error, 422);
            }

            $locked->update($changes($locked));

            $this->operationLogs->record(new OperationLogEntry(
                action: $action,
                actionName: $actionName,
                objectType: '生产异常',
                objectId: $locked->id,
                objectName: $locked->code,
                before: ['状态' => $before],
                after: ['状态' => $locked->statusLabel(), ...($after !== null ? $after($locked) : [])],
            ));

            return $this->load($locked);
        });
    }

    private function load(ProductionException $exception): ProductionException
    {
        return $exception->load(ProductionException::RELATIONS);
    }
}
