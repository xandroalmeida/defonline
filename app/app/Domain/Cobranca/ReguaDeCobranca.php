<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * STORY-079 / espec §3.7 e ADR-010 §4.2 — a régua de cobrança, sem banco nem rede.
 *
 * Linha do tempo de uma renovação recusada (t0 = a recusa que o provedor avisou):
 * `t0` assinatura em atraso e aviso por e-mail; `t0+48h`, `+96h` e `+144h` as 3 retentativas, sempre
 * sobre a MESMA cobrança do provedor (nunca uma cobrança nova: é isso que impede cobrar em dobro);
 * `t0+192h`, 48 h depois da última, quem não pagou tem o ciclo encerrado. O prazo extra dá tempo ao
 * webhook da última tentativa chegar antes de se decidir pelo encerramento.
 */
final class ReguaDeCobranca
{
    public const MAX_TENTATIVAS = 3;

    public const INTERVALO_HORAS = 48;

    /** O lembrete de renovação sai quando faltam 7 dias ou menos para o fim do ciclo pago. */
    public const AVISO_RENOVACAO_DIAS = 7;

    public static function proximaTentativa(CarbonInterface $base): CarbonImmutable
    {
        return CarbonImmutable::instance($base)->addHours(self::INTERVALO_HORAS);
    }

    public static function decidir(int $tentativasFeitas, ?CarbonInterface $proximaEm, CarbonInterface $agora): AcaoDaRegua
    {
        if ($proximaEm !== null && $proximaEm->greaterThan($agora)) {
            return AcaoDaRegua::Aguardar;
        }

        return $tentativasFeitas >= self::MAX_TENTATIVAS ? AcaoDaRegua::Encerrar : AcaoDaRegua::Retentar;
    }

    /** O fim do ciclo está a até 7 dias e ainda não passou. */
    public static function avisoDeRenovacaoDevido(CarbonInterface $fimDoCiclo, CarbonInterface $agora): bool
    {
        return $fimDoCiclo->greaterThan($agora) && $fimDoCiclo->lessThanOrEqualTo(CarbonImmutable::instance($agora)->addDays(self::AVISO_RENOVACAO_DIAS));
    }

    /** Quando a última retentativa acontece (para a tela "Última tentativa em ..."). */
    public static function ultimaTentativaEm(CarbonInterface $atrasoDesde): CarbonImmutable
    {
        return CarbonImmutable::instance($atrasoDesde)->addHours(self::INTERVALO_HORAS * self::MAX_TENTATIVAS);
    }

    /**
     * A cobrança é a do ciclo que ficou em aberto (a que vence ao fim do período pago), e não uma
     * cobrança antiga que o provedor reenviou fora de ordem. Folga de 2 dias: o provedor conta o
     * vencimento por data, e nós, pelo instante.
     */
    public static function cobrancaDoCicloEmAberto(?\DateTimeInterface $vencimentoDaCobranca, CarbonInterface $fimDoPeriodoPago): bool
    {
        return $vencimentoDaCobranca === null
            || CarbonImmutable::instance($vencimentoDaCobranca)->greaterThanOrEqualTo(CarbonImmutable::instance($fimDoPeriodoPago)->subDays(2)->startOfDay());
    }
}
