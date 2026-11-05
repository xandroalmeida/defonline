<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-082 × 083 — o que acontece com o desconto de cupom de uma assinatura quando o plano muda.
 *
 * - O crédito do plano atual usa o preço do ciclo em vigor, com o desconto ({@see precoDoCicloEmVigor()}).
 * - O cupom já vinculado NÃO é reaplicado (não há novo uso nem nova Invoice com cupom): o mesmo desconto
 *   segue, recalculado sobre o preço do plano novo, e só se o cupom cobre o plano e o ciclo novos.
 * - Troca de ciclo (mensal para anual e vice-versa) reinicia a contagem de ciclos, então o desconto termina
 *   nela: o número de ciclos do cupom é em ciclos do plano contratado.
 * - Se o desconto ainda valeria em ciclos futuros e não continua, ele termina na troca ({@see termina()}), e a
 *   tela de revisão avisa. O valor final nunca fica negativo ({@see PrecoComCupom}).
 */
final class DescontoNaTroca
{
    /** O que o usuário paga pelo ciclo em curso: o preço com o desconto desse ciclo, senão o contratado. */
    public static function precoDoCicloEmVigor(?DescontoDaAssinatura $atual, int $periodosPagos, int $precoSnapshot): int
    {
        return $atual !== null && $atual->existe() ? $atual->valorDoPeriodo(max(1, $periodosPagos)) : $precoSnapshot;
    }

    /**
     * O desconto sobre o plano novo, ou `null` se não há desconto a levar (não havia, não vale mais neste
     * ciclo, o cupom não cobre o plano novo, ou o ciclo mudou).
     */
    public static function sobreOPlanoNovo(
        ?DescontoDaAssinatura $atual,
        int $periodosPagos,
        bool $mesmoCiclo,
        bool $cupomCobre,
        TipoDesconto $tipo,
        int $valorDoCupom,
        int $precoNovoCheio,
        int $minimoCentavos,
    ): ?DescontoDaAssinatura {
        if ($atual === null || ! $mesmoCiclo || ! $cupomCobre || ! $atual->vale(max(1, $periodosPagos))) {
            return null;
        }

        $preco = PrecoComCupom::calcular($precoNovoCheio, $tipo, $valorDoCupom, $minimoCentavos);

        return $preco->desconto > 0 ? new DescontoDaAssinatura($precoNovoCheio, $preco->desconto, $atual->periodos) : null;
    }

    /** O desconto ainda valeria em ciclos futuros e não continua no plano novo: termina na troca. */
    public static function termina(?DescontoDaAssinatura $atual, int $periodosPagos, ?DescontoDaAssinatura $novo): bool
    {
        return $atual !== null && $novo === null && $atual->vale(max(1, $periodosPagos) + 1);
    }
}
