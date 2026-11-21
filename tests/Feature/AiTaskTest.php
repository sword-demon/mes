<?php

use App\Core\Audit\AuditContext;
use App\Core\Queue\AiTaskJob;
use App\Core\Queue\AiTaskResult;
use App\Core\Queue\Jobs\ExampleAiTaskJob;
use App\Modules\System\Model\AiTask;
use App\Modules\System\Model\User;
use App\Modules\System\Service\AiTaskService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class FailingAiTaskJob extends AiTaskJob
{
    public int $tries = 1;

    protected function execute(AiTask $task): AiTaskResult
    {
        throw new RuntimeException('示例任务失败');
    }
}

class FlakyAiTaskJob extends AiTaskJob
{
    public static int $attempts = 0;

    public int $tries = 2;

    protected function execute(AiTask $task): AiTaskResult
    {
        self::$attempts++;

        if (self::$attempts === 1) {
            throw new RuntimeException('瞬时失败');
        }

        return new AiTaskResult(['attempt' => self::$attempts], 3, 2);
    }
}

it('creates a pending ai task with actor, model and input snapshots', function () {
    $actor = User::factory()->create();

    $task = app(AiTaskService::class)->createPending(
        type: AiTask::TYPE_EXAMPLE,
        actor: $actor,
        actorIp: '203.0.113.9',
        model: 'demo-model',
        input: ['message' => '你好'],
        traceId: 'trace-ai-task',
    );

    expect($task->status)->toBe(AiTask::STATUS_PENDING)
        ->and($task->trace_id)->toBe('trace-ai-task')
        ->and($task->type)->toBe(AiTask::TYPE_EXAMPLE)
        ->and($task->actor_id)->toBe($actor->id)
        ->and($task->actor_ip)->toBe('203.0.113.9')
        ->and($task->model)->toBe('demo-model')
        ->and($task->input)->toBe(['message' => '你好'])
        ->and($task->output)->toBeNull()
        ->and($task->error)->toBeNull()
        ->and($task->prompt_tokens)->toBeNull()
        ->and($task->completion_tokens)->toBeNull()
        ->and($task->latency_ms)->toBeNull()
        ->and($task->started_at)->toBeNull()
        ->and($task->finished_at)->toBeNull();
});

it('creates the pending record before dispatching the example task', function () {
    Queue::fake();
    $actor = User::factory()->create();

    $task = app(AiTaskService::class)->dispatch(
        jobClass: ExampleAiTaskJob::class,
        type: AiTask::TYPE_EXAMPLE,
        actor: $actor,
        actorIp: '203.0.113.10',
        input: ['message' => 'hi'],
    );

    Queue::assertPushedOn(
        'ai',
        ExampleAiTaskJob::class,
        fn (ExampleAiTaskJob $job): bool => $job->aiTaskId === $task->id,
    );

    expect($task->fresh()->status)->toBe(AiTask::STATUS_PENDING);
});

it('marks a task running before the work starts', function () {
    $task = AiTask::factory()->create();

    app(AiTaskService::class)->markRunning($task);

    expect($task->fresh()->status)->toBe(AiTask::STATUS_RUNNING)
        ->and($task->fresh()->started_at)->not->toBeNull()
        ->and($task->fresh()->finished_at)->toBeNull();
});

it('runs the example task through the worker and stores the result', function () {
    config(['queue.default' => 'database']);
    $actor = User::factory()->create();

    $task = app(AiTaskService::class)->dispatch(
        jobClass: ExampleAiTaskJob::class,
        type: AiTask::TYPE_EXAMPLE,
        actor: $actor,
        actorIp: '203.0.113.11',
        input: ['message' => 'hello'],
        traceId: 'trace-e2e',
    );

    Artisan::call('queue:work', ['--once' => true, '--queue' => 'ai']);

    $task->refresh();

    expect($task->status)->toBe(AiTask::STATUS_SUCCEEDED)
        ->and($task->output)->toBe(['echo' => 'hello', 'length' => 5])
        ->and($task->started_at)->not->toBeNull()
        ->and($task->finished_at)->not->toBeNull()
        ->and($task->latency_ms)->toBeInt()
        ->and($task->error)->toBeNull();
});

it('marks a failed task with the error and latency', function () {
    config(['queue.default' => 'database']);
    Log::spy();
    $actor = User::factory()->create();

    $task = app(AiTaskService::class)->dispatch(
        jobClass: FailingAiTaskJob::class,
        type: AiTask::TYPE_EXAMPLE,
        actor: $actor,
        actorIp: '203.0.113.12',
    );

    Artisan::call('queue:work', ['--once' => true, '--queue' => 'ai']);

    $task->refresh();

    expect($task->status)->toBe(AiTask::STATUS_FAILED)
        ->and($task->trace_id)->not->toBeEmpty()
        ->and($task->error)->toContain('示例任务失败')
        ->and($task->finished_at)->not->toBeNull()
        ->and($task->latency_ms)->toBeInt();

    expect(DB::table('failed_jobs')->count())->toBe(1);

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $event, array $context): bool => $event === 'ai.task.failed'
            && $context['ai_task_id'] === $task->id
            && str_contains($context['error'], '示例任务失败'))
        ->once();
});

it('stores token usage and explicit latency when a task succeeds', function () {
    $task = AiTask::factory()->create();
    $service = app(AiTaskService::class);

    $service->markRunning($task);
    $service->markSucceeded(
        $task,
        ['ok' => true],
        latencyMs: 42,
        promptTokens: 11,
        completionTokens: 7,
    );

    expect($task->fresh()->status)->toBe(AiTask::STATUS_SUCCEEDED)
        ->and($task->fresh()->prompt_tokens)->toBe(11)
        ->and($task->fresh()->completion_tokens)->toBe(7)
        ->and($task->fresh()->latency_ms)->toBe(42);
});

it('marks a running task failed when the queue reports a terminal failure', function () {
    Log::spy();

    $task = AiTask::factory()->create([
        'status' => AiTask::STATUS_RUNNING,
        'started_at' => now()->subSecond(),
    ]);

    (new FailingAiTaskJob($task->id))->failed(new RuntimeException('任务超时'));

    expect($task->fresh()->status)->toBe(AiTask::STATUS_FAILED)
        ->and($task->fresh()->error)->toContain('任务超时')
        ->and($task->fresh()->latency_ms)->toBeInt();

    Log::shouldHaveReceived('error')
        ->with('ai.task.failed', ['ai_task_id' => $task->id, 'error' => '任务超时'])
        ->once();
});

it('skips jobs whose task is already terminal', function (string $status) {
    $task = AiTask::factory()->create([
        'status' => $status,
        'output' => ['kept' => true],
    ]);
    $service = app(AiTaskService::class);

    (new ExampleAiTaskJob($task->id))->handle($service, app(AuditContext::class));
    (new FailingAiTaskJob($task->id))->failed(new RuntimeException('迟到的失败'));

    expect($task->fresh()->status)->toBe($status)
        ->and($task->fresh()->output)->toBe(['kept' => true]);
})->with([AiTask::STATUS_SUCCEEDED, AiTask::STATUS_CANCELLED]);

it('fails when the ai task record is missing', function () {
    expect(fn () => (new ExampleAiTaskJob(9999))->handle(
        app(AiTaskService::class),
        app(AuditContext::class),
    ))
        ->toThrow(RuntimeException::class, 'AI 任务记录 9999 不存在');
});

it('reuses the same record when a task retries', function () {
    FlakyAiTaskJob::$attempts = 0;
    $service = app(AiTaskService::class);
    $task = $service->createPending(type: AiTask::TYPE_EXAMPLE);

    try {
        (new FlakyAiTaskJob($task->id))->handle($service, app(AuditContext::class));
    } catch (RuntimeException) {
    }

    expect($task->fresh()->status)->toBe(AiTask::STATUS_FAILED);

    (new FlakyAiTaskJob($task->id))->handle($service, app(AuditContext::class));

    expect($task->fresh()->status)->toBe(AiTask::STATUS_SUCCEEDED)
        ->and($task->fresh()->output)->toBe(['attempt' => 2])
        ->and($task->fresh()->prompt_tokens)->toBe(3)
        ->and($task->fresh()->completion_tokens)->toBe(2)
        ->and(AiTask::query()->count())->toBe(1);
});
