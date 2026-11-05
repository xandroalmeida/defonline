<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/**
 * STORY-073 CA-11 — por que não há saldo, com os dados que a tela precisa para dizer "em {data}"
 * e "suas {n} análises".
 */
final readonly class Bloqueio
{
    public function __construct(
        public MotivoBloqueio $motivo,
        public ?CarbonImmutable $data = null,
        public ?int $cotaDoCiclo = null,
        /** Só para `SemNada`: o teste grátis está disponível para este Usuário? */
        public bool $trialElegivel = false,
        /** Só para `CotaEsgotada`: a cota volta no próximo ciclo? Falso quando o plano foi cancelado. */
        public bool $renova = true,
    ) {}
}
