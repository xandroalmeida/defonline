<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use App\Support\DataExibicao;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * STORY-082 CA-1, CA-3, CA-4 — a regra de preço da troca de plano, pura.
 *
 * "Superior" é só o preço (o catálogo é configurável, não há código de plano): o preço do ciclo novo
 * dividido pelos meses dele, contra o que o usuário paga hoje por mês. Empate de preço só sobe se a cota
 * for maior. Quem desce, ou muda de anual para mensal (o crédito do ano não usado não vira dinheiro:
 * fora de escopo), troca no fim do ciclo.
 *
 * Upgrade no mesmo ciclo: paga-se a diferença pelo que resta do período, em proporção de tempo
 * (parcela do preço novo menos crédito do atual, ambos arredondados). Upgrade com troca de ciclo
 * (mensal para anual): o período novo começa hoje, pelo preço cheio, descontado o crédito do que
 * restava do atual. Em nenhum caso o valor é negativo.
 */
final class CalculoDeTroca
{
    /** Diferenças abaixo disto (R$ 5,00) são aplicadas sem cobrança (ajuste do ADR-010). */
    public const MINIMO_COBRANCA_CENTAVOS = 500;

    public static function calcular(
        int $precoAtualCentavos,
        CicloCobranca $cicloAtual,
        int $cotaAtual,
        CarbonInterface $inicioDoPeriodo,
        CarbonInterface $fimDoPeriodo,
        CarbonInterface $agora,
        int $precoNovoCentavos,
        CicloCobranca $cicloNovo,
        int $cotaNova,
        ?CarbonInterface $inicioDaCota = null,
        ?CarbonInterface $fimDaCota = null,
    ): PreviaDeTroca {
        $fim = CarbonImmutable::instance($fimDoPeriodo);
        $limite = self::fimNominal($fim, $inicioDoPeriodo)->getTimestamp();
        $total = max(1, $limite - $inicioDoPeriodo->getTimestamp());
        $restante = max(0, min($total, $limite - $agora->getTimestamp()));
        $mesmoCiclo = $cicloAtual === $cicloNovo;
        $imediata = self::ehUpgrade($precoAtualCentavos, $cicloAtual, $cotaAtual, $precoNovoCentavos, $cicloNovo, $cotaNova)
            && ! ($cicloAtual === CicloCobranca::Anual && $cicloNovo === CicloCobranca::Mensal);
        $dias = (int) ceil($restante / 86400);
        $credito = self::proporcional($precoAtualCentavos, $restante, $total);

        // STORY-091: a janela da cota em curso (o mês, ou o aniversário no anual); sem ela, o próprio período.
        $inicioDaCota ??= $inicioDoPeriodo;
        $fimDaCota = CarbonImmutable::instance($fimDaCota ?? $fimDoPeriodo);

        if (! $imediata) {
            return new PreviaDeTroca(false, $mesmoCiclo, $precoNovoCentavos, $precoAtualCentavos, $precoNovoCentavos, 0, 0, true, $dias, $fim, $fim, $cicloNovo, $cotaNova);
        }

        $parcela = $mesmoCiclo ? self::proporcional($precoNovoCentavos, $restante, $total) : $precoNovoCentavos;
        $aPagar = max(0, $parcela - $credito);
        $proxima = $mesmoCiclo ? $fim : $cicloNovo->vencimento($agora);

        // Período novo: cota cheia já. Mesmo período: só a parte proporcional (CotaExtraNoUpgrade) e a cheia na virada da cota.
        $cotaExtra = $mesmoCiclo ? CotaExtraNoUpgrade::calcular($cotaAtual, $cotaNova, $inicioDaCota, $fimDaCota, $agora) : $cotaNova;
        $cotaCheiaEm = $mesmoCiclo ? $fimDaCota : null;

        return new PreviaDeTroca(true, $mesmoCiclo, $precoNovoCentavos, $precoAtualCentavos, $parcela, $credito, $aPagar, $aPagar < self::MINIMO_COBRANCA_CENTAVOS, $dias, $proxima, $proxima, $cicloNovo, $cotaNova, cotaExtraAgora: $cotaExtra, cotaCheiaEm: $cotaCheiaEm);
    }

    /**
     * O vencimento gravado vai até o último segundo do dia (STORY-089), mas o ciclo pago tem o tamanho de
     * "início + n meses": a proporção do preço usa esse fim nominal, o mesmo dia no mesmo horário do início,
     * e não ganha horas só porque o acesso dura o dia inteiro.
     */
    private static function fimNominal(CarbonImmutable $fim, CarbonInterface $inicio): CarbonImmutable
    {
        if (! $fim->equalTo(DataExibicao::fimDoDia($fim))) {
            return $fim;
        }

        $horaDoInicio = DataExibicao::converter($inicio);

        return DataExibicao::converter($fim)->setTime($horaDoInicio->hour, $horaDoInicio->minute, $horaDoInicio->second);
    }

    public static function ehUpgrade(int $precoAtual, CicloCobranca $cicloAtual, int $cotaAtual, int $precoNovo, CicloCobranca $cicloNovo, int $cotaNova): bool
    {
        // Preço por mês, comparado em cruz para não depender de divisão.
        $novo = $precoNovo * $cicloAtual->meses();
        $atual = $precoAtual * $cicloNovo->meses();

        return $novo > $atual || ($novo === $atual && $cotaNova > $cotaAtual);
    }

    /** `valor × restante / total`, arredondado para o centavo mais próximo. */
    private static function proporcional(int $valor, int $restante, int $total): int
    {
        return intdiv($valor * $restante * 2 + $total, $total * 2);
    }
}
