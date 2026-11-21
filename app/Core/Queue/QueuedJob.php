<?php

namespace App\Core\Queue;

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
 * 子类定义构造函数时必须先调用 `parent::__construct()`，否则
 * `afterCommit` 不会生效。
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

    public function __construct()
    {
        $this->onQueue('default');
        $this->afterCommit();
    }
}
