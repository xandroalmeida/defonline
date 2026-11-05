<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\ReguaDeCobranca;
use App\Mail\AssinaturaEncerradaMessage;
use App\Mail\FalhaDeCobrancaMessage;
use App\Mail\RenovacaoProximaMessage;
use App\Models\Assinatura;
use App\Models\Plano;
use App\Models\Usuario;
use App\Support\Cobranca\RotasCobranca;
use App\Support\DataExibicao;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * STORY-079 — e-mails transacionais da renovação (espec §4.12). Quem chama decide QUANDO enviar e
 * grava que enviou; aqui só se monta e se envia. Falha de envio não derruba o fluxo de cobrança:
 * vira log e devolve `false` para o chamador tentar de novo.
 */
final class AvisosDeCobranca
{
    public function renovacaoProxima(Assinatura $a): bool
    {
        return $this->enviar($a, 'renovacao_proxima', fn (Usuario $u, string $plano): Mailable => new RenovacaoProximaMessage(
            $u->primeiroNome(),
            $plano,
            $a->ciclo === 'anual' ? 'anual' : 'mensal',
            $this->reais((int) $a->preco_snapshot_centavos),
            $this->data($a->proximo_vencimento),
            $this->linkDoPlano(),
        ));
    }

    public function falhaDeCobranca(Assinatura $a): bool
    {
        return $this->enviar($a, 'falha_de_cobranca', fn (Usuario $u, string $plano): Mailable => new FalhaDeCobrancaMessage(
            $u->primeiroNome(),
            $plano,
            $this->reais((int) $a->preco_snapshot_centavos),
            $this->data($a->proxima_tentativa_em),
            $this->data($a->atraso_desde === null ? null : ReguaDeCobranca::ultimaTentativaEm($a->atraso_desde)),
            $this->linkDoPlano(),
        ));
    }

    public function assinaturaEncerrada(Assinatura $a): bool
    {
        return $this->enviar($a, 'assinatura_encerrada', fn (Usuario $u, string $plano): Mailable => new AssinaturaEncerradaMessage(
            $u->primeiroNome(),
            $plano,
            RotasCobranca::url('planos') ?? url('/planos'),
        ));
    }

    /** Onde o assinante atualiza o cartão: "Meu plano" (STORY-081); até lá, a página de planos. */
    private function linkDoPlano(): string
    {
        return RotasCobranca::url(RotasCobranca::MEU_PLANO) ?? url('/planos');
    }

    /** @param \Closure(Usuario, string): Mailable $montar */
    private function enviar(Assinatura $a, string $aviso, \Closure $montar): bool
    {
        try {
            $usuario = Usuario::query()->findOrFail($a->usuario_id);
            $plano = $a->plano_id === null ? 'DEFOnline' : Plano::query()->findOrFail($a->plano_id)->nome;

            Mail::to($usuario->email)->send($montar($usuario, $plano));

            return true;
        } catch (Throwable $e) {
            Log::error('cobranca.aviso_nao_enviado', [
                'module' => 'cobranca',
                'action' => 'avisar_assinante',
                'aviso' => $aviso,
                'assinatura_id' => $a->id,
                'exception' => $e::class,
            ]);

            return false;
        }
    }

    private function reais(int $centavos): string
    {
        return 'R$ '.number_format($centavos / 100, 2, ',', '.');
    }

    private function data(?CarbonInterface $quando): string
    {
        return DataExibicao::data($quando);
    }
}
