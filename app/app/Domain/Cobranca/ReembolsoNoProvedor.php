<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** Resposta canônica de {@see PaymentProvider::refund()}. `detalhe` explica a recusa, sem dado de cartão. */
final readonly class ReembolsoNoProvedor
{
    public function __construct(
        public ResultadoReembolso $resultado,
        public ?string $detalhe = null,
    ) {}
}
