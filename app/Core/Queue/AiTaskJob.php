<?php

namespace App\Core\Queue;

use App\Modules\System\Model\AiTask;
use App\Modules\System\Service\AiTaskService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * AI 任务基类：把 `sys_ai_tasks` 的状态流转固定在执行入口。
 *
 * 子类只实现 `execute()`；重试会复用同一个记录 ID，开始时重置上一轮
 * 的错误、结束时间与耗时，避免重试留下脏状态。
 */
abstract class AiTaskJob extends AiQueuedJob
{
    final public function __construct(public readonly int $aiTaskId)
    {
        parent::__construct();
    }

    final public function handle(AiTaskService $tasks): void
    {
        $task = AiTask::query()->find($this->aiTaskId);

        if ($task === null) {
            Log::error('ai.task.missing', ['ai_task_id' => $this->aiTaskId]);

            throw new RuntimeException("AI 任务记录 {$this->aiTaskId} 不存在");
        }

        // 重复投递不能重跑已经结束的任务，尤其是真实模型调用会产生二次费用。
        if (in_array($task->status, [AiTask::STATUS_SUCCEEDED, AiTask::STATUS_CANCELLED], true)) {
            Log::info('ai.task.skipped', ['ai_task_id' => $this->aiTaskId, 'status' => $task->status]);

            return;
        }

        $tasks->markRunning($task);
        $startedAt = microtime(true);

        try {
            $result = $this->execute($task);
            $tasks->markSucceeded(
                $task,
                $result->output,
                $this->elapsedMilliseconds($startedAt),
                $result->promptTokens,
                $result->completionTokens,
            );
        } catch (Throwable $exception) {
            $tasks->markFailed(
                $task,
                $exception->getMessage() ?: $exception::class,
                $this->elapsedMilliseconds($startedAt),
            );

            throw $exception;
        }
    }

    abstract protected function execute(AiTask $task): AiTaskResult;

    public function failed(?Throwable $exception): void
    {
        $task = AiTask::query()->find($this->aiTaskId);

        if ($task === null
            || in_array($task->status, [
                AiTask::STATUS_SUCCEEDED,
                AiTask::STATUS_FAILED,
                AiTask::STATUS_CANCELLED,
            ], true)) {
            return;
        }

        $message = $exception?->getMessage() ?: '任务执行失败';
        Log::error('ai.task.failed', ['ai_task_id' => $this->aiTaskId, 'error' => $message]);

        app(AiTaskService::class)->markFailed(
            $task,
            $message,
        );
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }
}
