<?php

namespace App\Core\Queue\Jobs;

use App\Core\Queue\AiTaskJob;
use App\Core\Queue\AiTaskResult;
use App\Modules\System\Model\AiTask;

/**
 * 不调用模型的示例任务，用于验证派发、状态流转与结果落库。
 */
class ExampleAiTaskJob extends AiTaskJob
{
    protected function execute(AiTask $task): AiTaskResult
    {
        $message = (string) (($task->input ?? [])['message'] ?? '');

        return new AiTaskResult([
            'echo' => $message,
            'length' => mb_strlen($message),
        ]);
    }
}
