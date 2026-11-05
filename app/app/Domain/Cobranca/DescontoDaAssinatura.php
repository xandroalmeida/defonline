<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * STORY-083 CA-7, CA-8, CA-9 — o desconto de cupom de uma assinatura, como retrato do que foi
 * contratado (desativar o cupom depois não muda nada). Vale pelos primeiros `$periodos` ciclos
 * cobrados (`null` = enquanto a assinatura durar). O período 1 é o do checkout; a renovação N+1 é
 * o período N+1.
 */
final readonly class DescontoDaAssinatura
{
    public function __construct(
        /** Preço do ciclo sem desconto, o que vale depois que o desconto acaba. */
        public int $cheio,
        /** Desconto por ciclo, em centavos (0 = sem desconto). */
        public int $desconto,
        /** Quantos ciclos têm desconto; `null` = todos. */
        public ?int $periodos,
    ) {}

    public function existe(): bool
    {
        return $this->desconto > 0;
    }

    /** O ciclo `$periodo` (1 = o primeiro) tem desconto? */
    public function vale(int $periodo): bool
    {
        return $this->existe() && ($this->periodos === null || $periodo <= $this->periodos);
    }

    /** Quanto se cobra no ciclo `$periodo`. */
    public function valorDoPeriodo(int $periodo): int
    {
        return $this->vale($periodo) ? max(0, $this->cheio - $this->desconto) : $this->cheio;
    }

    /** O ciclo `$periodo` é gratuito (desconto total)? */
    public function gratuito(int $periodo): bool
    {
        return $this->vale($periodo) && $this->valorDoPeriodo($periodo) === 0;
    }

    /** O último ciclo com desconto é o `$periodosPagos`? Então o valor no provedor volta ao cheio agora. */
    public function acabaApos(int $periodosPagos): bool
    {
        return $this->existe() && $this->periodos !== null && $periodosPagos === $this->periodos;
    }

    /** Fim do último ciclo com desconto; `null` se o desconto não acaba. */
    public function fim(CicloCobranca $ciclo, CarbonInterface $inicio): ?CarbonImmutable
    {
        return $this->existe() && $this->periodos !== null ? $ciclo->vencimento($inicio, $this->periodos) : null;
    }

    /** Faltam `$dias` ou menos para o desconto acabar (e ele ainda não acabou)? */
    public function pertoDoFim(CicloCobranca $ciclo, CarbonInterface $inicio, CarbonInterface $agora, int $dias = 7): bool
    {
        $fim = $this->fim($ciclo, $inicio);

        return $fim !== null && $fim->greaterThan($agora) && $fim->lessThanOrEqualTo(CarbonImmutable::instance($agora)->addDays($dias));
    }
}
