<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use DateTimeImmutable;

final readonly class SessaoCheckout
{
    public function __construct(
        public ReferenciaProvedor $referencia,
        public string $url,
        public DateTimeImmutable $expiraEm,
    ) {}
}
