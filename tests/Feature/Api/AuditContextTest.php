<?php

use App\Core\Audit\AuditContext;
use App\Modules\System\Model\OperationLog;
use App\Modules\System\Model\Role;
use App\Modules\System\Model\User;
use App\Modules\System\Service\OperationLogService;
use App\Modules\System\ValueObject\OperationLogEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;

function createUserPayload(string $email): array
{
    return [
        'name' => '审计用户',
        'email' => $email,
        'password' => 'password-123',
    ];
}

it('records an operation as human by default', function () {
    $actor = superAdmin();

    bearer($actor)->postJson('/api/v1/system/users', createUserPayload('audit-default@mes.test'))
        ->assertOk();

    $log = OperationLog::query()->sole();

    expect($log->source)->toBe('human')
        ->and($log->trace_id)->toBeNull();
});

it('records the source, actor, ip and trace id from the audit context', function () {
    $actor = superAdmin();
    $target = User::factory()->create(['name' => '李四']);

    app(AuditContext::class)->set(AuditContext::SOURCE_AI, $actor, '10.0.0.9', 'trace-abc');

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/roles", [
        'role_ids' => [Role::factory()->create()->id],
    ])->assertOk();

    $log = OperationLog::query()->sole();

    expect($log->source)->toBe('ai')
        ->and($log->trace_id)->toBe('trace-abc')
        ->and($log->ip)->toBe('10.0.0.9')
        ->and($log->user_id)->toBe($actor->id);
});

it('keeps an explicit actor and ip ahead of the audit context', function () {
    $contextActor = User::factory()->create(['name' => '上下文操作人']);
    $explicitActor = User::factory()->create(['name' => '显式操作人']);

    app(AuditContext::class)->set(AuditContext::SOURCE_AI, $contextActor, '10.0.0.9', 'trace-abc');

    $log = app(OperationLogService::class)->record(
        new OperationLogEntry(
            action: 'smoke.test',
            actionName: '冒烟测试',
            objectType: '测试',
            objectId: 1,
            objectName: '对象',
        ),
        $explicitActor,
        '192.168.1.5',
    );

    expect($log->user_id)->toBe($explicitActor->id)
        ->and($log->ip)->toBe('192.168.1.5')
        ->and($log->source)->toBe('ai');
});

it('filters operation logs by source, trace id and keyword', function () {
    $actor = superAdmin();
    $target = User::factory()->create(['name' => '王五']);

    app(AuditContext::class)->set(AuditContext::SOURCE_AI, $actor, '10.0.0.9', 'trace-filter');
    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/roles", [
        'role_ids' => [Role::factory()->create()->id],
    ])->assertOk();

    app(AuditContext::class)->reset();
    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/password", [
        'password' => 'password-456',
    ])->assertOk();

    expect(OperationLog::query()->count())->toBe(2);

    bearer($actor)->getJson('/api/v1/system/operation-logs?source=ai')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.trace_id', 'trace-filter');

    bearer($actor)->getJson('/api/v1/system/operation-logs?trace_id=trace-filter')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.source', 'ai');

    bearer($actor)->getJson('/api/v1/system/operation-logs?'.http_build_query(['keyword' => '重置密码']))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.action', 'system.user.password-reset');

    bearer($actor)->getJson('/api/v1/system/operation-logs?'.http_build_query(['object_type' => '用户']))
        ->assertOk()
        ->assertJsonCount(2, 'data.items');
});

it('rolls back the audit log with the business transaction', function () {
    app(AuditContext::class)->set(
        AuditContext::SOURCE_HUMAN,
        User::factory()->create(),
        '127.0.0.1',
    );

    try {
        DB::transaction(function (): void {
            app(OperationLogService::class)->record(new OperationLogEntry(
                action: 'smoke.rollback',
                actionName: '回滚测试',
                objectType: '测试',
                objectId: 1,
                objectName: '对象',
            ));

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
        // Expected: the transaction rolls back, taking the log row with it.
    }

    expect(OperationLog::query()->count())->toBe(0);
});

it('exposes the source options for the log filters', function () {
    $actor = superAdmin();

    bearer($actor)->getJson('/api/v1/system/operation-logs/options')
        ->assertOk()
        ->assertJsonPath('data.sources', ['human', 'ai', 'system']);
});

it('never writes the password into the audit log', function () {
    $actor = superAdmin();
    $target = User::factory()->create();

    bearer($actor)->putJson("/api/v1/system/users/{$target->id}/password", [
        'password' => 'super-secret-password',
    ])->assertOk();

    $log = OperationLog::query()->sole();

    expect($log->summary)->not->toContain('super-secret-password')
        ->and($log->changes)->toBeNull();
});
