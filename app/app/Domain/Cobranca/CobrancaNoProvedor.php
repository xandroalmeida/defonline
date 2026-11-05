<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use DateTimeImmutable;

/** Retrato canônico de uma cobrança, lido do provedor no momento da conferência. */
final readonly class CobrancaNoProvedor
{
    public function __construct(
        public ReferenciaProvedor $referencia,
        public EstadoCobranca $estado,
        public int $valorCentavos,
        public ?string $checkoutId = null,
        public ?string $referenciaInterna = null,
        public ?DateTimeImmutable $pagoEm = null,
    ) {}
}
