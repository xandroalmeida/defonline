<?php

declare(strict_types=1);

namespace App\Providers;

use App\Observabilidade\Listeners\CollectJobMetrics;
use App\Services\Rfb\CnpjaRfbCnpjClient;
use App\Services\Rfb\MockRfbCnpjClient;
use App\Services\Rfb\ReceitawsRfbCnpjClient;
use App\Services\Rfb\RfbCnpjClient;
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

        // STORY-018 CA-4 — bind do provedor RFB conforme `config('services.rfb.provider')`.
        // IDR-004 fixa os três valores aceitos; o `default` é exception explícita para
        // detectar typo em env (`RFB_PROVIDER=cpnja`) já no boot, não na primeira consulta.
        $this->app->singleton(RfbCnpjClient::class, function () {
            $provider = (string) config('services.rfb.provider', 'mock');

            return match ($provider) {
                'mock' => new MockRfbCnpjClient,
                'cnpja' => new CnpjaRfbCnpjClient,
                'receitaws' => new ReceitawsRfbCnpjClient,
                default => throw new \InvalidArgumentException(
                    "Provedor RFB desconhecido: '{$provider}'. Use mock|cnpja|receitaws (IDR-004).",
                ),
            };
        });
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
