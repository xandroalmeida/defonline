<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\CobrancaNoProvedor;
use App\Domain\Cobranca\EstadoCobranca;
use App\Domain\Cobranca\PaymentProvider;
use App\Domain\Cobranca\ReferenciaProvedor;
use App\Domain\Cobranca\ReguaDeCobranca;
use App\Domain\Cobranca\StatusAssinatura;
use App\Models\Assinatura;
use App\Models\CobrancaConfig;
use App\Observabilidade\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * STORY-079 CA-3 e CA-6 — o provedor avisou que a cobrança de renovação de uma assinatura nossa não
 * foi paga (recusada ou vencida). Põe a assinatura "Em atraso", agenda a primeira retentativa para 48 h
 * depois e avisa o assinante com o link para atualizar o cartão.
 *
 * - O estado ATUAL da cobrança manda (ADR-010 §3): se ela já está paga, o aviso de falha chegou fora
 *   de ordem e o que vale é o pagamento; se foi cancelada ou estornada, não há o que cobrar.
 * - Só assinatura `ativa` entra em atraso. Quem já está em atraso não reinicia a régua (a recusa e o
 *   vencimento da mesma cobrança chegam como dois eventos), e cancelada ou encerrada não é tocada.
 * - Com a cobrança desligada (STORY-073) nada acontece.
 *
 * Retorno: `[status do evento, id da Invoice]` no formato de {@see ProcessarEvento}.
 */
final class FalhaDeRenovacao
{
    public function __construct(
        private readonly PaymentProvider $provedor,
        private readonly ConfirmarAssinatura $assinaturas,
        private readonly AvisosDeCobranca $avisos,
    ) {}

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    public function registrar(Assinatura $assinatura, string $cobrancaId): array
    {
        if (! CobrancaConfig::ligada()) {
            return ['ignorado', null];
        }

        $cobranca = $this->provedor->getCharge(new ReferenciaProvedor($assinatura->provedor ?? $this->provedor->nome(), $cobrancaId));

        if ($cobranca->estado === EstadoCobranca::Paga) {
            // O aviso de falha chegou depois do pagamento: vale o pagamento.
            return $this->assinaturas->aplicar(null, $cobranca, $assinatura->provedor ?? $this->provedor->nome());
        }

        if ($cobranca->estado->final()) {
            return ['ignorado', null];
        }

        // Cartão novo de uma assinatura em atraso (STORY-081): a troca parou a régua da cobrança antiga e a 1ª
        // cobrança do cartão novo foi recusada. A régua recomeça sobre ela, senão a assinatura ficaria em
        // atraso para sempre, sem retentativa nem encerramento.
        if ($assinatura->status === StatusAssinatura::EmAtraso && $assinatura->atraso_cobranca_id === null) {
            return $this->retomarRegua($assinatura, $cobrancaId);
        }

        if (! $this->entraEmAtraso($assinatura, $cobranca)) {
            return ['ignorado', null];
        }

        $atrasada = DB::transaction(function () use ($assinatura, $cobrancaId): ?Assinatura {
            $a = Assinatura::query()->lockForUpdate()->findOrFail($assinatura->id);

            if ($a->status !== StatusAssinatura::Ativa || $a->trial) {
                return null;
            }

            $agora = now();
            $a->forceFill([
                'status' => StatusAssinatura::EmAtraso,
                'atraso_desde' => $agora,
                'atraso_cobranca_id' => $cobrancaId,
                'tentativas_cobranca' => 0,
                'proxima_tentativa_em' => ReguaDeCobranca::proximaTentativa($agora),
                'atraso_aviso_em' => null,
            ])->save();

            AuditLogger::log(
                action: 'cobranca.renovacao_recusada',
                subjectType: 'Assinatura',
                subjectId: $a->id,
                actorType: 'system',
                usuarioId: $a->usuario_id,
                after: ['cobranca' => $cobrancaId, 'proxima_tentativa_em' => $a->proxima_tentativa_em?->toIso8601String()],
            );

            return $a;
        });

        if ($atrasada === null) {
            return ['ignorado', null];
        }

        if ($this->avisos->falhaDeCobranca($atrasada)) {
            $atrasada->forceFill(['atraso_aviso_em' => now()])->save();
        }

        return ['processado', null];
    }

    /** @return array{0: 'processado'|'ignorado', 1: string|null} */
    private function retomarRegua(Assinatura $assinatura, string $cobrancaId): array
    {
        $retomada = DB::transaction(function () use ($assinatura, $cobrancaId): bool {
            $a = Assinatura::query()->lockForUpdate()->findOrFail($assinatura->id);

            if ($a->status !== StatusAssinatura::EmAtraso || $a->atraso_cobranca_id !== null) {
                return false;
            }

            $a->forceFill([
                'atraso_cobranca_id' => $cobrancaId,
                'tentativas_cobranca' => 0,
                'proxima_tentativa_em' => ReguaDeCobranca::proximaTentativa(now()),
                'atraso_aviso_em' => null,
            ])->save();

            AuditLogger::log(
                action: 'cobranca.regua_retomada_apos_troca_de_cartao',
                subjectType: 'Assinatura',
                subjectId: $a->id,
                actorType: 'system',
                usuarioId: $a->usuario_id,
                after: ['cobranca' => $cobrancaId],
            );

            return true;
        });

        return $retomada ? ['processado', null] : ['ignorado', null];
    }

    /** Só a cobrança do ciclo em aberto de uma assinatura em dia coloca em atraso. */
    private function entraEmAtraso(Assinatura $assinatura, CobrancaNoProvedor $cobranca): bool
    {
        return $assinatura->status === StatusAssinatura::Ativa
            && ! $assinatura->trial
            && ReguaDeCobranca::cobrancaDoCicloEmAberto($cobranca->vencimento, $assinatura->proximo_vencimento);
    }
}
