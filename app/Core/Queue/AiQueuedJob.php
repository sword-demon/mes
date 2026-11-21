<?php

namespace App\Core\Queue;

/**
 * AI 队列任务基类。
 *
 * 模型调用允许更长的执行时间与更少的重试，并与业务队列使用独立 worker，
 * 避免一次长调用阻塞业务任务。
 *
 * 注意：Laravel 以任务载荷中的 `$timeout` 与 `$tries` 为准，worker 命令行的
 * `--timeout` / `--tries` 只是兜底，所以 AI 任务必须继承本类而不是
 * `QueuedJob`，否则会退回 60 秒超时。
 */
abstract class AiQueuedJob extends QueuedJob
{
    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        ?int $auditActorId = null,
        ?string $auditActorIp = null,
        ?string $auditTraceId = null,
    ) {
        parent::__construct($auditActorId, $auditActorIp, $auditTraceId);

        $this->onQueue('ai');
    }
}
