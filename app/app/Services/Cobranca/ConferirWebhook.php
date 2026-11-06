<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ProvedorIndisponivel;
use App\Domain\Cobranca\SituacaoDoWebhook;
use App\Models\CobrancaConfig;
use Illuminate\Support\Facades\Log;

/**
 * STORY-101 — o provedor pausa a fila depois de falhas seguidas, e ela não volta sozinha. Esta rotina olha
 * o cadastro, alerta a equipe e retoma a fila, e o provedor reenvia o que guardou. Webhook desligado é
 * decisão de alguém: só alerta. A conta de sandbox é compartilhada: só o cadastro com a NOSSA URL é tocado.
 */
final class ConferirWebhook
{
    public const OK = 'ok';

    public const RETOMADO = 'retomado';

    public const FALHOU = 'falhou';

    public function __construct(private readonly PaymentProvider $provedor) {}

    public function executar(): string
    {
        if (! CobrancaConfig::ligada()) {
            return self::OK;
        }

        try {
            $situacao = $this->provedor->situacaoDoWebhook($this->url());
        } catch (ProvedorIndisponivel) {
            Log::error('cobranca.webhook_nao_conferido', ['module' => 'cobranca', 'action' => 'conferir_webhook']);

            return self::FALHOU;
        }

        if ($situacao === null) {
            // Fora de produção o webhook usa a URL do túnel, que muda a cada subida.
            if (! app()->isProduction()) {
                return self::OK;
            }

            Log::error('cobranca.webhook_nao_cadastrado', ['module' => 'cobranca', 'action' => 'conferir_webhook', 'prioridade' => 'P1']);

            return self::FALHOU;
        }

        return $this->agir($situacao);
    }

    private function agir(SituacaoDoWebhook $situacao): string
    {
        if (! $situacao->ativo) {
            Log::error('cobranca.webhook_desligado', ['module' => 'cobranca', 'action' => 'conferir_webhook', 'prioridade' => 'P1']);

            return self::FALHOU;
        }

        if (! $situacao->filaPausada) {
            if ($situacao->penalizado) {
                Log::error('cobranca.webhook_penalizado', ['module' => 'cobranca', 'action' => 'conferir_webhook']);
            }

            return self::OK;
        }

        Log::error('cobranca.webhook_fila_pausada', ['module' => 'cobranca', 'action' => 'conferir_webhook', 'prioridade' => 'P1']);

        try {
            $this->provedor->retomarWebhook($situacao->id);
        } catch (ProvedorIndisponivel) {
            Log::error('cobranca.webhook_fila_nao_retomada', ['module' => 'cobranca', 'action' => 'conferir_webhook', 'prioridade' => 'P1']);

            return self::FALHOU;
        }

        Log::info('cobranca.webhook_fila_retomada', ['module' => 'cobranca', 'action' => 'conferir_webhook']);

        return self::RETOMADO;
    }

    private function url(): string
    {
        $url = config('services.payments.webhook_url');

        return is_string($url) && $url !== '' ? $url : rtrim((string) config('app.url'), '/').'/webhooks/pagamentos';
    }
}
