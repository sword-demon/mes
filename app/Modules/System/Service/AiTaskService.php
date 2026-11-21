<?php

namespace App\Modules\System\Service;

use App\Core\Queue\AiTaskJob;
use App\Modules\System\Model\AiTask;
use App\Modules\System\Model\User;
use Illuminate\Support\Str;

class AiTaskService
{
    /**
     * Create the pending record first, then enqueue the job that owns it.
     *
     * @param  class-string<AiTaskJob>  $jobClass
     * @param  array<string, mixed>  $input
     */
    public function dispatch(
        string $jobClass,
        string $type,
        User $actor,
        string $actorIp,
        ?string $model = null,
        array $input = [],
        ?string $traceId = null,
    ): AiTask {
        $task = $this->createPending($type, $actor, $actorIp, $model, $input, $traceId);

        dispatch(new $jobClass($task->id, $task->actor_id, $task->actor_ip, $task->trace_id));

        return $task;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createPending(
        string $type,
        ?User $actor = null,
        ?string $actorIp = null,
        ?string $model = null,
        array $input = [],
        ?string $traceId = null,
    ): AiTask {
        return AiTask::query()->create([
            'trace_id' => $traceId ?: (string) Str::uuid(),
            'type' => $type,
            'status' => AiTask::STATUS_PENDING,
            'actor_id' => $actor?->id,
            'actor_ip' => $actorIp,
            'model' => $model,
            'input' => $input,
        ]);
    }

    public function markRunning(AiTask $task): void
    {
        $task->update([
            'status' => AiTask::STATUS_RUNNING,
            'started_at' => now(),
            'finished_at' => null,
            'latency_ms' => null,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'output' => null,
            'error' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $output
     */
    public function markSucceeded(
        AiTask $task,
        array $output,
        ?int $latencyMs = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
    ): void {
        $task->update([
            'status' => AiTask::STATUS_SUCCEEDED,
            'output' => $output,
            'error' => null,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'latency_ms' => $this->latency($task, $latencyMs),
            'finished_at' => now(),
        ]);
    }

    public function markFailed(
        AiTask $task,
        string $error,
        ?int $latencyMs = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null,
    ): void {
        $task->update([
            'status' => AiTask::STATUS_FAILED,
            'output' => null,
            // MySQL TEXT 上限 64KB；中文按 3 字节计，留出余量避免失败写入自身报错。
            'error' => Str::limit($error, 16000, '…'),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'latency_ms' => $this->latency($task, $latencyMs),
            'finished_at' => now(),
        ]);
    }

    private function latency(AiTask $task, ?int $latencyMs): ?int
    {
        if ($latencyMs !== null) {
            return $latencyMs;
        }

        return $task->started_at === null
            ? null
            : (int) round($task->started_at->diffInMilliseconds(now()));
    }
}
