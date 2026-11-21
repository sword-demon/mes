<?php

namespace App\Core\Queue;

use App\Core\Audit\AuditContext;
use App\Modules\System\Model\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * 队列任务基类。
 *
 * 统一重试、退避、超时与入队时机：任务在业务事务提交后才进入队列，
 * 执行超时即标记失败，避免一个卡住的任务永久占用 worker。
 *
 * 子类定义构造函数时必须先调用 `parent::__construct()` 并透传审计参数，
 * 否则 `afterCommit` 或审计身份会丢失。
 */
abstract class QueuedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public bool $failOnTimeout = true;

    public function __construct(
        public ?int $auditActorId = null,
        public ?string $auditActorIp = null,
        public ?string $auditTraceId = null,
    ) {
        $this->onQueue('default');
        $this->afterCommit();
    }

    /**
     * Run the callback under the payload's audit identity and always clear it,
     * so a long-running worker cannot leak one job's actor into the next.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withAuditContext(AuditContext $context, string $source, callable $callback): mixed
    {
        $context->set(
            $source,
            $this->auditActorId === null ? null : User::query()->find($this->auditActorId),
            $this->auditActorIp,
            $this->auditTraceId,
        );

        try {
            return $callback();
        } finally {
            $context->reset();
        }
    }
}
