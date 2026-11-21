<?php

use App\Core\Audit\AuditContext;
use App\Core\DataScope\DataScopeGuard;
use App\Core\Exceptions\ApiException;
use App\Core\Queue\AiTaskJob;
use App\Core\Queue\AiTaskResult;
use App\Core\Queue\Jobs\ExampleAiTaskJob;
use App\Modules\Organization\Model\Factory;
use App\Modules\Organization\Model\ProductionLine;
use App\Modules\Organization\Model\Workshop;
use App\Modules\System\Model\AiTask;
use App\Modules\System\Model\OperationLog;
use App\Modules\System\Model\User;
use App\Modules\System\Service\AiTaskService;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class AuditedAiTaskJob extends AiTaskJob
{
    protected function execute(AiTask $task): AiTaskResult
    {
        app(OperationLogService::class)->record(new OperationLogEntry(
            action: 'ai.example.audit',
            actionName: 'AI 示例审计',
            objectType: 'AI 任务',
            objectId: $task->id,
            objectName: $task->trace_id,
        ));

        return new AiTaskResult(['ok' => true]);
    }
}

class FailingAuditedAiTaskJob extends AiTaskJob
{
    public int $tries = 1;

    protected function execute(AiTask $task): AiTaskResult
    {
        throw new RuntimeException('审计任务失败');
    }
}

it('carries the actor, ip and trace in the queued job payload', function () {
    Queue::fake();
    $actor = User::factory()->create();

    $task = app(AiTaskService::class)->dispatch(
        jobClass: ExampleAiTaskJob::class,
        type: AiTask::TYPE_EXAMPLE,
        actor: $actor,
        actorIp: '203.0.113.7',
        input: ['message' => 'hi'],
        traceId: 'trace-payload',
    );

    Queue::assertPushedOn(
        'ai',
        ExampleAiTaskJob::class,
        fn (ExampleAiTaskJob $job): bool => $job->aiTaskId === $task->id
            && $job->auditActorId === $actor->id
            && $job->auditActorIp === '203.0.113.7'
            && $job->auditTraceId === 'trace-payload',
    );
});

it('uses the queued actor for data scope checks', function () {
    [$factoryA, $lineA] = factoryWithLines();
    $factoryB = Factory::factory()->create();
    $workshopB = Workshop::factory()->create(['factory_id' => $factoryB->id]);
    $lineB = ProductionLine::factory()->create(['workshop_id' => $workshopB->id]);

    $actor = userWithPermissions(['data.scope.line'], 'workshop_director');
    $actor->update(['factory_id' => $factoryA->id]);
    $actor->productionLines()->sync([$lineA->id]);

    app(AuditContext::class)->set(AuditContext::SOURCE_AI, $actor, '198.51.100.20', 'trace-scope');

    $guard = app(DataScopeGuard::class);

    expect(fn () => $guard->ensureLine($lineB->id))
        ->toThrow(ApiException::class, '生产产线不在您的数据范围内');

    $guard->ensureLine($lineA->id);
});

it('writes queue operation logs with the payload actor, ip, ai source and trace', function () {
    $actor = User::factory()->create();
    $service = app(AiTaskService::class);
    $task = $service->createPending(
        type: 'audit',
        actor: $actor,
        actorIp: '198.51.100.7',
        traceId: 'trace-audit',
    );

    (new AuditedAiTaskJob($task->id, $actor->id, '198.51.100.7', 'trace-audit'))
        ->handle($service, app(AuditContext::class));

    $log = OperationLog::query()->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->ip)->toBe('198.51.100.7')
        ->and($log->source)->toBe(AuditContext::SOURCE_AI)
        ->and($log->trace_id)->toBe('trace-audit');

    expect(app(AuditContext::class)->source())->toBe(AuditContext::SOURCE_HUMAN)
        ->and(app(AuditContext::class)->actor())->toBeNull()
        ->and(app(AuditContext::class)->ip())->toBeNull()
        ->and(app(AuditContext::class)->traceId())->toBeNull();
});

it('carries the audit identity through a real queue worker', function () {
    config(['queue.default' => 'database']);
    $actor = User::factory()->create();

    app(AiTaskService::class)->dispatch(
        jobClass: AuditedAiTaskJob::class,
        type: 'audit',
        actor: $actor,
        actorIp: '203.0.113.8',
        traceId: 'trace-worker',
    );

    Artisan::call('queue:work', ['--once' => true, '--queue' => 'ai']);

    $log = OperationLog::query()->sole();

    expect($log->user_id)->toBe($actor->id)
        ->and($log->ip)->toBe('203.0.113.8')
        ->and($log->source)->toBe(AuditContext::SOURCE_AI)
        ->and($log->trace_id)->toBe('trace-worker')
        ->and(app(AuditContext::class)->actor())->toBeNull();
});

it('does not leak audit context between sequential jobs and groups logs by trace', function () {
    $actorA = User::factory()->create();
    $actorB = User::factory()->create();
    $service = app(AiTaskService::class);

    $taskA = $service->createPending(
        type: 'audit',
        actor: $actorA,
        actorIp: '198.51.100.1',
        traceId: 'trace-shared',
    );
    $taskB = $service->createPending(
        type: 'audit',
        actor: $actorB,
        actorIp: '198.51.100.2',
        traceId: 'trace-shared',
    );

    (new AuditedAiTaskJob($taskA->id, $actorA->id, '198.51.100.1', 'trace-shared'))
        ->handle($service, app(AuditContext::class));

    expect(app(AuditContext::class)->actor())->toBeNull();

    (new AuditedAiTaskJob($taskB->id, $actorB->id, '198.51.100.2', 'trace-shared'))
        ->handle($service, app(AuditContext::class));

    expect(app(AuditContext::class)->actor())->toBeNull();

    $logs = OperationLog::query()->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->user_id)->toBe($actorA->id)
        ->and($logs[0]->ip)->toBe('198.51.100.1')
        ->and($logs[1]->user_id)->toBe($actorB->id)
        ->and($logs[1]->ip)->toBe('198.51.100.2');

    $viewer = userWithPermissions(['system.logs.index']);

    bearer($viewer)->getJson('/api/v1/system/operation-logs?trace_id=trace-shared')
        ->assertOk()
        ->assertJsonCount(2, 'data.items');
});

it('clears the audit context when the job fails', function () {
    $actor = User::factory()->create();
    $service = app(AiTaskService::class);
    $task = $service->createPending(
        type: 'audit',
        actor: $actor,
        actorIp: '198.51.100.9',
        traceId: 'trace-fail',
    );

    try {
        (new FailingAuditedAiTaskJob($task->id, $actor->id, '198.51.100.9', 'trace-fail'))
            ->handle($service, app(AuditContext::class));
    } catch (RuntimeException) {
    }

    expect(app(AuditContext::class)->source())->toBe(AuditContext::SOURCE_HUMAN)
        ->and(app(AuditContext::class)->actor())->toBeNull()
        ->and(app(AuditContext::class)->ip())->toBeNull()
        ->and(app(AuditContext::class)->traceId())->toBeNull();
});
