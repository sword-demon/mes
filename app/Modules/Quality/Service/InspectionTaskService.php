<?php

namespace App\Modules\Quality\Service;

use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Modules\Process\Model\RouteOperation;
use App\Modules\Production\Model\ProcessRecord;
use App\Modules\Production\Model\ProductionOrder;
use App\Modules\Production\Service\ProcessRecordService;
use App\Modules\Quality\Model\InspectionImage;
use App\Modules\Quality\Model\InspectionItem;
use App\Modules\Quality\Model\InspectionTask;
use App\Modules\System\Model\User;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectionTaskService
{
    public function __construct(
        private readonly ProcessRecordService $processRecords,
        private readonly DataScopeGuard $dataScope,
        private readonly OperationLogService $operationLogs,
    ) {}

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
            $this->dataScope->ensure($order);
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
                    ->where('inspection_cycle', $record->inspection_cycle)
                    ->whereNull('result')
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

            $requiredItems = InspectionItem::query()
                ->where('status', true)
                ->with('standard')
                ->orderBy('id')
                ->get();

            if ($requiredItems->isEmpty()) {
                throw new ApiException('没有启用的检验项目，不能创建检验任务', 422);
            }

            $missingStandard = $requiredItems->first(fn (InspectionItem $item): bool => $item->standard === null);

            if ($missingStandard !== null) {
                throw new ApiException("检验项目「{$missingStandard->name}」未维护标准，不能创建检验任务", 422);
            }

            $task = InspectionTask::query()->create([
                'production_order_id' => (int) $data['production_order_id'],
                'process_record_id' => $record?->id,
                'route_operation_id' => $operation->id,
                'inspection_cycle' => $record === null ? 0 : $record->inspection_cycle,
                'inspection_type' => $type,
                'remark' => $data['remark'] ?? null,
            ]);
            $task->requiredItems()->sync($requiredItems->modelKeys());

            $this->operationLogs->record(new OperationLogEntry(
                action: 'quality.inspection.create',
                actionName: '新建检验任务',
                objectType: '检验任务',
                objectId: $task->id,
                objectName: $this->taskLabel($task),
                after: ['检验类型' => InspectionTask::TYPE_LABELS[$type] ?? $type],
            ));

            return $task;
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
            $this->dataScope->ensure($locked);

            if ($locked->result !== null) {
                throw new ApiException('检验任务已判定，不能重复提交', 422);
            }

            $record = null;

            if ($locked->inspection_type === InspectionTask::IPQC && $locked->process_record_id !== null) {
                $record = ProcessRecord::query()
                    ->lockForUpdate()
                    ->whereKey($locked->process_record_id)
                    ->firstOrFail();

                if ($locked->inspection_cycle !== $record->inspection_cycle) {
                    throw new ApiException('检验任务已过期，请按当前工序轮次重新检验', 422);
                }
            }

            $measurements = [];

            foreach ($data['items'] as $measurement) {
                $measurements[(int) $measurement['inspection_item_id']] = (string) $measurement['measured_value'];
            }

            // The task captures its inspection set at creation, so later
            // standard edits cannot silently widen or narrow the judgement.
            $requiredItems = $locked->requiredItems()->with('standard')->get();

            if ($requiredItems->isEmpty()) {
                throw new ApiException('检验任务未配置检验项目，不能判定', 422);
            }

            $requiredByItem = $requiredItems->keyBy('id');
            $rows = [];
            $failures = [];
            $allPassed = true;

            foreach ($measurements as $itemId => $measured) {
                $item = $requiredByItem->get($itemId);

                if ($item === null) {
                    throw new ApiException('检验项目不在本任务范围内，不能判定', 422);
                }

                if ($item->standard === null) {
                    throw new ApiException("检验项目「{$item->name}」未维护标准，不能判定", 422);
                }

                $minimum = (string) $item->standard->min_value;
                $maximum = (string) $item->standard->max_value;

                if (! is_numeric($measured) || ! is_numeric($minimum) || ! is_numeric($maximum)) {
                    throw new ApiException('实测值或标准值不是有效数字，不能判定', 422);
                }

                $passed = bccomp($measured, $minimum, 3) >= 0
                    && bccomp($measured, $maximum, 3) <= 0;
                $allPassed = $allPassed && $passed;

                if (! $passed) {
                    $failures[] = sprintf(
                        '%s=%s（%s~%s）',
                        $item->name,
                        number_format((float) $measured, 3, '.', ''),
                        $item->standard->min_value,
                        $item->standard->max_value,
                    );
                }

                $rows[] = [
                    'inspection_item_id' => $item->id,
                    'measured_value' => $measured,
                    'standard_value' => $item->standard->standard_value,
                    'min_value' => $item->standard->min_value,
                    'max_value' => $item->standard->max_value,
                    'passed' => $passed,
                ];
            }

            $missing = $requiredItems->first(fn (InspectionItem $item): bool => ! array_key_exists($item->id, $measurements));

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

            if ($record !== null) {
                $this->processRecords->inspect(
                    $record,
                    $allPassed,
                    $allPassed ? null : ($remark ?? mb_substr('检验不合格：'.implode('；', $failures), 0, 255)),
                );
            }

            $this->operationLogs->record(new OperationLogEntry(
                action: 'quality.inspection.record',
                actionName: '录入检验结果',
                objectType: '检验任务',
                objectId: $locked->id,
                objectName: $this->taskLabel($locked),
                after: [
                    '检验结果' => $allPassed ? '合格' : '不合格',
                    '不合格项数量' => count($failures),
                    '检验轮次' => $locked->inspection_cycle,
                ],
            ));

            return $locked->load(self::RELATIONS);
        });
    }

    /**
     * Executions have no own code; tasks are identified by order and type.
     */
    private function taskLabel(InspectionTask $task): string
    {
        $orderCode = $task->productionOrder?->code;

        return ($orderCode === null ? '' : $orderCode.' ')
            .($task->inspection_type === InspectionTask::IPQC ? 'IPQC' : 'OQC')
            .'#'.$task->id;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, InspectionImage>
     */
    public function addImages(InspectionTask $task, array $files): Collection
    {
        $this->dataScope->ensure($task);

        $images = DB::transaction(function () use ($task, $files): Collection {
            $created = collect($files)->map(function (UploadedFile $file) use ($task): InspectionImage {
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

            $this->operationLogs->record(new OperationLogEntry(
                action: 'quality.inspection.add-images',
                actionName: '上传检验图片',
                objectType: '检验任务',
                objectId: $task->id,
                objectName: $this->taskLabel($task),
                after: ['图片数量' => $created->count()],
            ));

            return $created;
        });

        return $images;
    }

    public const array RELATIONS = [
        'productionOrder.product',
        'routeOperation.process',
        'processRecord.routeOperation.process',
        'inspector',
        'results.inspectionItem',
        'requiredItems.standard',
        'images',
    ];
}
