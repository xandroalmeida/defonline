<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/** STORY-073 — o que a regra de consumo precisa saber da assinatura (ou do trial) do Usuário. */
final readonly class AssinaturaVista
{
    public function __construct(
        public string $id,
        public bool $trial,
        public StatusAssinatura $status,
        public int $cotaSnapshot,
        public int $cotaRestante,
        public CarbonImmutable $vencimento,
    ) {}
}
