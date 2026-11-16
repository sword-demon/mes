<?php

namespace App\Modules\Production\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionException;
use App\Modules\System\Model\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductionExceptionService
{
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

                if ($orderId !== null
                    && $record !== null
                    && (int) $record->productionTask->production_order_id !== $orderId) {
                    throw new ApiException('工序执行记录不属于该生产订单', 422);
                }

                $orderId ??= $record?->productionTask->production_order_id;

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
        );
    }

    /**
     * @param  Closure(ProductionException): array<string, mixed>  $changes
     */
    private function transition(
        ProductionException $exception,
        string $requiredStatus,
        string $error,
        Closure $changes,
    ): ProductionException {
        return DB::transaction(function () use ($exception, $requiredStatus, $error, $changes): ProductionException {
            $locked = ProductionException::query()->lockForUpdate()->findOrFail($exception->id);

            if ($locked->status !== $requiredStatus) {
                throw new ApiException($error, 422);
            }

            $locked->update($changes($locked));

            return $this->load($locked);
        });
    }

    private function load(ProductionException $exception): ProductionException
    {
        return $exception->load(ProductionException::RELATIONS);
    }
}
