<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-091 CA-2 — a cota contratada é retrato. A renovação mantém a do `cota_snapshot`, mesmo que o
 * catálogo tenha mudado; só vale a do catálogo de hoje quando o assinante muda de plano (a troca
 * agendada), porque aí ele escolheu o plano com a cota que a tela mostrou.
 */
final class CotaContratada
{
    public static function naRenovacao(int $cotaContratada, string $planoAtualId, string $planoDaRenovacaoId, int $cotaDoCatalogo): int
    {
        return $planoAtualId === $planoDaRenovacaoId ? $cotaContratada : $cotaDoCatalogo;
    }
}
