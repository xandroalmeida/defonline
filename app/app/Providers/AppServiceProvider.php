<?php

declare(strict_types=1);

namespace App\Providers;

use App\Observabilidade\Listeners\CollectJobMetrics;
use App\Services\Rfb\MockRfbCnpjClient;
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

        // STORY-015 CA-6 — bind do provedor RFB conforme `config('services.rfb.provider')`.
        // A STORY-018 substituirá os branches `cnpja` e `receitaws` pelos clientes reais;
        // enquanto isso, todas as opções caem no mock para que a abstração esteja viva e
        // exercitada antes da ativação. IDR-004 prescreve este desenho.
        $this->app->singleton(RfbCnpjClient::class, function () {
            $provider = (string) config('services.rfb.provider', 'mock');

            return match ($provider) {
                'mock', 'cnpja', 'receitaws' => new MockRfbCnpjClient,
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
