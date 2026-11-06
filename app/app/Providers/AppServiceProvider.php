<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Motor\FonteRecomendacoes;
use App\Infrastructure\Cobranca\Asaas\AsaasPaymentProvider;
use App\Infrastructure\Cobranca\Fake\FakePaymentProvider;
use App\Infrastructure\Cobranca\Nenhum\ProvedorNaoConfigurado;
use App\Observabilidade\AlertaDeCobranca;
use App\Observabilidade\Listeners\CollectJobMetrics;
use App\Services\Rfb\CnpjaRfbCnpjClient;
use App\Services\Rfb\MockRfbCnpjClient;
use App\Services\Rfb\ReceitawsRfbCnpjClient;
use App\Services\Rfb\RfbCnpjClient;
use App\Support\RecomendacoesNoBanco;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
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
        $this->app->bind(FonteRecomendacoes::class, RecomendacoesNoBanco::class);

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

        // STORY-076 / ADR-010 — provedor de pagamento escolhido por `services.payments.provider`.
        // O fake nunca sobe em produção: cobrança de mentira aprovada em produção seria dinheiro perdido.
        // STORY-093 CA-1 — provedor NÃO definido em produção, ou Asaas com configuração incompleta, não derruba o
        // contêiner (agendadores com exit 1, webhook com 500): entra o `ProvedorNaoConfigurado`, que não cobra nada,
        // rejeita o webhook e impede ligar a cobrança. Valor explícito errado (typo, `fake` em produção) segue
        // estourando: é erro de deploy e tem que aparecer.
        $this->app->singleton(PaymentProvider::class, function () {
            $provider = (string) config('services.payments.provider');
            $producao = $this->app->environment('production');

            if ($provider === '') {
                if ($producao) {
                    return new ProvedorNaoConfigurado('PAYMENTS_PROVIDER não está definido (use asaas).');
                }

                $provider = 'fake';
            }

            return match ($provider) {
                'fake' => $producao
                    ? throw new \InvalidArgumentException('O provedor de pagamento fake não pode ser usado em produção.')
                    : new FakePaymentProvider(config('services.payments.webhook_token')),
                'asaas' => $this->asaas(),
                default => throw new \InvalidArgumentException(
                    "Provedor de pagamento desconhecido: '{$provider}'. Use fake|asaas (ADR-010).",
                ),
            };
        });
    }

    private function asaas(): PaymentProvider
    {
        try {
            return new AsaasPaymentProvider(
                config('services.asaas.base_url'),
                config('services.asaas.api_key'),
                config('services.asaas.webhook_token'),
                (int) config('services.asaas.timeout', 30),
            );
        } catch (ProvedorIndisponivel $e) {
            return new ProvedorNaoConfigurado($e->getMessage());
        }
    }

    public function boot(): void
    {
        Event::listen(JobProcessing::class, [CollectJobMetrics::class, 'onProcessing']);
        Event::listen(JobProcessed::class, [CollectJobMetrics::class, 'onProcessed']);
        Event::listen(JobFailed::class, [CollectJobMetrics::class, 'onFailed']);

        // STORY-086 CA-5 — todo `Log::error` com module=cobranca vira alerta no canal único da equipe.
        Event::listen(MessageLogged::class, AlertaDeCobranca::class);

        // Rate limit do login (ADR-001 §Autenticação padrão — 5/min por IP+email).
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // STORY-076 — teto folgado para o webhook (o Asaas em rajada), só para barrar abuso.
        RateLimiter::for('webhook-pagamentos', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
    }
}
