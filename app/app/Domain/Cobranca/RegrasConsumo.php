<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-073 / espec §4.3 — a regra de consumo, sem banco: cota primeiro, depois o crédito do lote
 * que vence antes; sem nenhum dos dois, o motivo do bloqueio.
 */
final class RegrasConsumo
{
    public static function decidir(SnapshotCarteira $carteira): Decisao
    {
        $assinatura = $carteira->assinatura;

        if ($assinatura !== null && self::daAcesso($assinatura, $carteira) && $assinatura->cotaRestante > 0 && ! self::testeBarradoPelaEmpresa($assinatura, $carteira)) {
            return Decisao::cota($assinatura->id);
        }

        $lote = self::loteMaisProximoDeVencer($carteira);

        if ($lote !== null) {
            return Decisao::credito($lote->id);
        }

        return Decisao::bloquear(self::motivo($carteira));
    }

    /**
     * STORY-084 — acesso ao Raio X do Negócio (DRM): plano ativo ou trial vigente, nunca crédito avulso
     * (PDR-020). Lançar não consome cota, então cota esgotada não bloqueia. `null` = acesso liberado.
     */
    public static function bloqueioDoDrm(SnapshotCarteira $carteira): ?Bloqueio
    {
        $assinatura = $carteira->assinatura;

        if ($assinatura === null) {
            return new Bloqueio(MotivoBloqueio::SemNada, trialElegivel: $carteira->trialElegivel);
        }

        if (self::daAcesso($assinatura, $carteira)) {
            return null;
        }

        return self::bloqueioDeAssinaturaEncerrada($assinatura);
    }

    /** Teste ou plano encerrado; se o plano era gratuito pelo desconto e não tem cartão, o desconto acabou. */
    private static function bloqueioDeAssinaturaEncerrada(AssinaturaVista $assinatura): Bloqueio
    {
        $motivo = match (true) {
            $assinatura->trial => MotivoBloqueio::TesteEncerrado,
            $assinatura->semCartao => MotivoBloqueio::DescontoAcabouSemCartao,
            default => MotivoBloqueio::PlanoEncerrado,
        };

        return new Bloqueio($motivo, $assinatura->vencimento);
    }

    /** O trial não vale para uma empresa que já gastou análise de trial em outra conta (STORY-074 CA-2); crédito comprado vale. */
    private static function testeBarradoPelaEmpresa(AssinaturaVista $assinatura, SnapshotCarteira $carteira): bool
    {
        return $assinatura->trial && $carteira->empresaJaEmTesteDeOutraConta;
    }

    /**
     * Ativa e cancelada dão acesso até o fim do ciclo pago. Em atraso o acesso segue durante as retentativas
     * (espec §3.7, Termo cl. 7): o bloqueio só vem quando a régua encerra o ciclo e a assinatura deixa de ser
     * vigente (STORY-092 CA-4). Encerrada, não.
     */
    private static function daAcesso(AssinaturaVista $assinatura, SnapshotCarteira $carteira): bool
    {
        return $assinatura->status === StatusAssinatura::EmAtraso
            || (($assinatura->status === StatusAssinatura::Ativa || $assinatura->status === StatusAssinatura::Cancelada)
                && $assinatura->vencimento->greaterThan($carteira->agora));
    }

    /** Vencido não se consome, mesmo que o lote ainda esteja marcado como ativo (CA-4). */
    private static function loteMaisProximoDeVencer(SnapshotCarteira $carteira): ?LoteVisto
    {
        $validos = array_filter(
            $carteira->lotes,
            fn (LoteVisto $l): bool => $l->status === StatusLote::Ativo && $l->restante > 0 && $l->expiraEm->greaterThan($carteira->agora),
        );

        usort($validos, fn (LoteVisto $a, LoteVisto $b): int => [$a->expiraEm, $a->compradoEm, $a->id] <=> [$b->expiraEm, $b->compradoEm, $b->id]);

        return $validos[0] ?? null;
    }

    private static function motivo(SnapshotCarteira $carteira): Bloqueio
    {
        $assinatura = $carteira->assinatura;

        if ($assinatura !== null) {
            return self::motivoDaAssinatura($assinatura, $carteira);
        }

        $vencidos = array_filter(
            $carteira->lotes,
            fn (LoteVisto $l): bool => $l->restante > 0 && $l->expiraEm->lessThanOrEqualTo($carteira->agora),
        );

        if ($vencidos !== []) {
            $ultimo = max(array_map(fn (LoteVisto $l) => $l->expiraEm, $vencidos));

            return new Bloqueio(MotivoBloqueio::CreditosVencidos, $ultimo);
        }

        return new Bloqueio(MotivoBloqueio::SemNada, trialElegivel: $carteira->trialElegivel);
    }

    private static function motivoDaAssinatura(AssinaturaVista $assinatura, SnapshotCarteira $carteira): Bloqueio
    {
        // Em atraso só chega aqui sem cota: não há renovação a esperar, o que destrava é o cartão (o aviso, com cota, é {@see AvisoDeAtraso}).
        if ($assinatura->status === StatusAssinatura::EmAtraso) {
            return new Bloqueio(MotivoBloqueio::EmAtraso, $assinatura->vencimento);
        }

        if (! self::daAcesso($assinatura, $carteira)) {
            return self::bloqueioDeAssinaturaEncerrada($assinatura);
        }

        if (self::testeBarradoPelaEmpresa($assinatura, $carteira)) {
            return new Bloqueio(MotivoBloqueio::TesteEmpresaJaAnalisada, $assinatura->vencimento);
        }

        if ($assinatura->trial) {
            return new Bloqueio(MotivoBloqueio::TesteUsado, $assinatura->vencimento);
        }

        return new Bloqueio(
            MotivoBloqueio::CotaEsgotada,
            $assinatura->vencimento,
            $assinatura->cotaSnapshot,
            renova: $assinatura->status === StatusAssinatura::Ativa,
        );
    }
}
