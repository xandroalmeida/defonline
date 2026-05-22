<?php

declare(strict_types=1);

namespace App\Providers;

use App\Observabilidade\Listeners\CollectJobMetrics;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CollectJobMetrics::class);
    }

    public function boot(): void
    {
        Event::listen(JobProcessing::class, [CollectJobMetrics::class, 'onProcessing']);
        Event::listen(JobProcessed::class, [CollectJobMetrics::class, 'onProcessed']);
        Event::listen(JobFailed::class, [CollectJobMetrics::class, 'onFailed']);

        // Rate limit do login (ADR-001 §Autenticação padrão — 5/min por IP+email).
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
