<?php

use App\Core\Queue\AiQueuedJob;
use App\Core\Queue\Jobs\QueueProbe;
use App\Core\Queue\QueuedJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;

class FailingQueueProbe extends QueuedJob
{
    public int $tries = 1;

    public function handle(): void
    {
        throw new RuntimeException('queue probe failed');
    }
}

it('defines the redis queue with a retry window longer than the longest timeout', function () {
    $redis = config('queue.connections.redis');

    expect($redis['driver'])->toBe('redis')
        ->and($redis['retry_after'])->toBeGreaterThanOrEqual(240)
        ->and($redis['after_commit'])->toBeTrue()
        ->and($redis['block_for'])->toBe(5);
});

it('ships a base job with retry, timeout, backoff and after-commit defaults', function () {
    $job = new QueueProbe('test');

    expect($job)->toBeInstanceOf(QueuedJob::class)
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(60)
        ->and($job->backoff)->toBe([10, 60, 300])
        ->and($job->failOnTimeout)->toBeTrue()
        ->and($job->queue)->toBe('default')
        ->and($job->afterCommit)->toBeTrue();
});

it('dispatches the probe to the default queue', function () {
    Queue::fake();

    QueueProbe::dispatch('probe');

    Queue::assertPushedOn('default', QueueProbe::class);
});

it('runs the probe and writes a log entry', function () {
    Log::spy();

    (new QueueProbe('probe'))->handle();

    Log::shouldHaveReceived('info')->with('queue.probe', ['label' => 'probe'])->once();
});

it('ships an AI job base with a longer timeout on the ai queue', function () {
    $job = new class extends AiQueuedJob
    {
        public function handle(): void {}
    };

    expect($job->tries)->toBe(2)
        ->and($job->timeout)->toBe(180)
        ->and($job->queue)->toBe('ai')
        ->and($job->afterCommit)->toBeTrue();
});

it('runs a dispatched probe through a real queue worker', function () {
    config(['queue.default' => 'database']);
    Log::spy();

    QueueProbe::dispatch('worker-probe');

    expect(DB::table('jobs')->count())->toBe(1);

    Artisan::call('queue:work', ['--once' => true, '--tries' => 1]);

    Log::shouldHaveReceived('info')->with('queue.probe', ['label' => 'worker-probe'])->once();
    expect(DB::table('jobs')->count())->toBe(0);
});

it('moves a job that exhausts its attempts to the failed queue', function () {
    config(['queue.default' => 'database']);

    FailingQueueProbe::dispatch();

    Artisan::call('queue:work', ['--once' => true]);

    expect(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('jobs')->count())->toBe(0);
});
