<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** Identificador de um recurso no provedor (checkout ou cobrança). */
final readonly class ReferenciaProvedor
{
    public function __construct(
        public string $provedor,
        public string $id,
    ) {}
}
