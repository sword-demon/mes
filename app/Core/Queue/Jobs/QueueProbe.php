<?php

namespace App\Core\Queue\Jobs;

use App\Core\Queue\QueuedJob;
use Illuminate\Support\Facades\Log;

/**
 * 队列探活任务。
 *
 * 用于验证 worker 是否在工作：派发后在 worker 日志里留下一条记录，
 * 运维与测试都可通过它确认队列链路通畅。
 */
class QueueProbe extends QueuedJob
{
    public function __construct(public readonly string $label = 'default')
    {
        parent::__construct();
    }

    public function handle(): void
    {
        Log::info('queue.probe', ['label' => $this->label]);
    }
}
