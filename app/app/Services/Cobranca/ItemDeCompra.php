<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

/** STORY-083 — o que se compra, para o cupom saber se cobre: um plano em um ciclo, ou um pacote de créditos. */
final readonly class ItemDeCompra
{
    public function __construct(
        public int $precoCentavos,
        public ?string $planoId = null,
        public ?string $ciclo = null,
        public ?string $pacoteId = null,
    ) {}
}
