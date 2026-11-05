<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/** STORY-073 — o que a regra de consumo precisa saber de um lote de créditos. */
final readonly class LoteVisto
{
    public function __construct(
        public string $id,
        public StatusLote $status,
        public int $restante,
        public CarbonImmutable $compradoEm,
        public CarbonImmutable $expiraEm,
    ) {}

    /** STORY-081 CA-1 — vence dentro de `$dias` (e ainda não venceu). */
    public function pertoDeVencer(CarbonImmutable $agora, int $dias): bool
    {
        return $this->expiraEm->greaterThan($agora) && $this->expiraEm->lessThanOrEqualTo($agora->addDays($dias));
    }
}
