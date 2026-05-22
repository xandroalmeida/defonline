<?php

declare(strict_types=1);

use App\Jobs\BaseJob;
use App\Observabilidade\Listeners\CollectJobMetrics;
use App\Support\RequestId;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;

/**
 * Job dummy só para exercitar o listener via sync queue (RefreshDatabase).
 */
final class DummyMetricsJob extends BaseJob
{
    protected function handleJob(): void
    {
        // no-op — só queremos os eventos JobProcessing/JobProcessed do worker.
    }
}

afterEach(function () {
    RequestId::reset();
});

it('populates job_metrics on successful job processing via the sync queue', function () {
    RequestId::set('0190b1aa-0000-7000-8000-aaaaaaaaaaaa');

    DummyMetricsJob::dispatchSync();

    $row = DB::table('job_metrics')
        ->where('job_class', DummyMetricsJob::class)
        ->orderByDesc('inserido_em')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->status)->toBe('ok');
    expect($row->request_id)->toBe('0190b1aa-0000-7000-8000-aaaaaaaaaaaa');
    expect($row->tentativas)->toBeGreaterThanOrEqual(1);
    expect($row->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('records status=failed on JobFailed event', function () {
    $listener = app(CollectJobMetrics::class);

    $jobStub = Mockery::mock(Job::class, function (MockInterface $m) {
        $m->shouldReceive('getJobId')->andReturn('failjob-1');
        $m->shouldReceive('resolveName')->andReturn('App\\Jobs\\DummyMetricsJob');
        $m->shouldReceive('getQueue')->andReturn('default');
        $m->shouldReceive('attempts')->andReturn(3);
    });

    $listener->onProcessing(new JobProcessing('sync', $jobStub));
    $listener->onFailed(new JobFailed('sync', $jobStub, new RuntimeException('boom')));

    $row = DB::table('job_metrics')->where('status', 'failed')->latest('inserido_em')->first();

    expect($row)->not->toBeNull();
    expect($row->job_class)->toBe('App\\Jobs\\DummyMetricsJob');
    expect($row->tentativas)->toBe(3);
});

it('keeps duration_ms at 0 when JobProcessed fires without a matching onProcessing', function () {
    $listener = app(CollectJobMetrics::class);

    $jobStub = Mockery::mock(Job::class, function (MockInterface $m) {
        $m->shouldReceive('getJobId')->andReturn('orphan-1');
        $m->shouldReceive('resolveName')->andReturn('App\\Jobs\\OrphanJob');
        $m->shouldReceive('getQueue')->andReturn(null);
        $m->shouldReceive('attempts')->andReturn(1);
    });

    $listener->onProcessed(new JobProcessed('sync', $jobStub));

    $row = DB::table('job_metrics')->where('job_class', 'App\\Jobs\\OrphanJob')->first();

    expect($row)->not->toBeNull();
    expect($row->duration_ms)->toBe(0);
    // queue NULL no event vira 'default' (fallback).
    expect($row->queue)->toBe('default');
});

it('logs a warning when the job_metrics insert fails (table dropped)', function () {
    DB::statement('DROP TABLE job_metrics');

    Log::shouldReceive('warning')
        ->atLeast()->once()
        ->withArgs(fn (string $msg) => $msg === 'job_metrics_insert_failed');

    $listener = app(CollectJobMetrics::class);
    $jobStub = Mockery::mock(Job::class, function (MockInterface $m) {
        $m->shouldReceive('getJobId')->andReturn('broken-1');
        $m->shouldReceive('resolveName')->andReturn('App\\Jobs\\Broken');
        $m->shouldReceive('getQueue')->andReturn('default');
        $m->shouldReceive('attempts')->andReturn(1);
    });

    $listener->onProcessing(new JobProcessing('sync', $jobStub));
    $listener->onProcessed(new JobProcessed('sync', $jobStub));
});
