<?php

namespace App\Modules\Quality\Service;

use App\Core\Exceptions\ApiException;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Service\ProcessRecordService;
use App\Modules\Quality\Model\InspectionImage;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\System\Model\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectionTaskService
{
    public function __construct(private readonly ProcessRecordService $processRecords) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function create(array $data): InspectionTask
    {
        return DB::transaction(function () use ($data): InspectionTask {
            $type = (string) $data['inspection_type'];
            $order = ProductionOrder::query()->findOrFail((int) $data['production_order_id']);
            $record = null;
            $operation = null;

            if (($data['process_record_id'] ?? null) !== null) {
                $record = ProcessRecord::query()
                    ->with(['productionTask', 'routeOperation'])
                    ->lockForUpdate()
                    ->findOrFail((int) $data['process_record_id']);

                if ($record->productionTask->production_order_id !== (int) $data['production_order_id']) {
                    throw new ApiException('工序不属于该生产订单', 422);
                }

                $operation = $record->routeOperation;
            }

            if ($type === InspectionTask::IPQC) {
                if ($record === null) {
                    throw new ApiException('IPQC 检验必须关联待检验工序', 422);
                }

                if ($record->status !== ProcessRecord::INSPECTING) {
                    throw new ApiException('工序当前状态不是待检验，不能创建 IPQC 任务', 422);
                }

                $hasPending = InspectionTask::query()
                    ->where('process_record_id', $record->id)
                    ->whereNull('result')
                    ->where('created_at', '>', $record->completed_at ?? $record->created_at)
                    ->exists();

                if ($hasPending) {
                    throw new ApiException('该工序已有待判定的检验任务', 422);
                }
            } elseif ($record !== null) {
                throw new ApiException('OQC 检验不关联工序执行记录', 422);
            } else {
                $operation = RouteOperation::query()
                    ->whereKey((int) ($data['route_operation_id'] ?? 0))
                    ->where('route_id', $order->route_id)
                    ->first();

                if ($operation === null) {
                    throw new ApiException('OQC 检验必须关联该订单工艺路线中的工序', 422);
                }
            }

            return InspectionTask::query()->create([
                'production_order_id' => (int) $data['production_order_id'],
                'process_record_id' => $record?->id,
                'route_operation_id' => $operation->id,
                'inspection_type' => $type,
                'remark' => $data['remark'] ?? null,
            ]);
        });
    }

    /**
     * Record every measured value, judge each item against its standard and
     * release or rework the process record tied to an IPQC task.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ApiException
     */
    public function record(InspectionTask $task, array $data, User $inspector): InspectionTask
    {
        return DB::transaction(function () use ($task, $data, $inspector): InspectionTask {
            $locked = InspectionTask::query()->lockForUpdate()->whereKey($task->getKey())->firstOrFail();

            if ($locked->result !== null) {
                throw new ApiException('检验任务已判定，不能重复提交', 422);
            }

            $measurements = [];

            foreach ($data['items'] as $measurement) {
                $measurements[(int) $measurement['inspection_item_id']] = (float) $measurement['measured_value'];
            }

            // Without a per-process item mapping, every enabled item is the
            // inspection set; a partial submission would let one good reading
            // pass the whole task.
            $enabled = InspectionItem::query()->where('status', true)->with('standard')->get();
            $enabledItems = $enabled->keyBy('id');
            $rows = [];
            $allPassed = true;

            foreach ($measurements as $itemId => $measured) {
                $item = $enabledItems->get($itemId);

                if ($item === null) {
                    throw new ApiException('检验项目不存在或已停用，不能判定', 422);
                }

                if ($item->standard === null) {
                    throw new ApiException("检验项目「{$item->name}」未维护标准，不能判定", 422);
                }

                $minimum = (float) $item->standard->min_value;
                $maximum = (float) $item->standard->max_value;
                $passed = $measured >= $minimum && $measured <= $maximum;
                $allPassed = $allPassed && $passed;

                $rows[] = [
                    'inspection_item_id' => $item->id,
                    'measured_value' => $measured,
                    'standard_value' => $item->standard->standard_value,
                    'min_value' => $item->standard->min_value,
                    'max_value' => $item->standard->max_value,
                    'passed' => $passed,
                ];
            }

            $missing = $enabled->first(fn (InspectionItem $item): bool => ! array_key_exists($item->id, $measurements));

            if ($missing !== null) {
                throw new ApiException("检验项目「{$missing->name}」未录入实测值", 422);
            }

            $remark = isset($data['remark']) && trim((string) $data['remark']) !== ''
                ? (string) $data['remark']
                : null;

            $locked->update([
                'result' => $allPassed ? InspectionTask::PASSED : InspectionTask::FAILED,
                'inspector_id' => $inspector->id,
                'inspected_at' => now(),
                'remark' => $remark ?? $locked->remark,
            ]);
            $locked->results()->createMany($rows);

            if ($locked->inspection_type === InspectionTask::IPQC && $locked->process_record_id !== null) {
                $record = ProcessRecord::query()->whereKey($locked->process_record_id)->firstOrFail();

                $this->processRecords->inspect(
                    $record,
                    $allPassed,
                    $allPassed ? null : ($remark ?? '检验项目超差'),
                );
            }

            return $locked->load(self::RELATIONS);
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, InspectionImage>
     */
    public function addImages(InspectionTask $task, array $files): Collection
    {
        return collect($files)->map(function (UploadedFile $file) use ($task): InspectionImage {
            $path = $file->store("inspection-images/{$task->id}", 'public');

            if ($path === false) {
                throw new ApiException('检验图片保存失败', 500);
            }

            try {
                return $task->images()->create([
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                ]);
            } catch (Throwable $exception) {
                Storage::disk('public')->delete($path);

                throw $exception;
            }
        });
    }

    public const array RELATIONS = [
        'productionOrder.product',
        'routeOperation.process',
        'processRecord.routeOperation.process',
        'inspector',
        'results.inspectionItem',
        'images',
    ];
}
