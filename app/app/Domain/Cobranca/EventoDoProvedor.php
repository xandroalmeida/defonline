<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * Evento canônico. `eventoId` é o id do evento no provedor (chave da idempotência de entrada);
 * os demais ids são dicas para achar a Invoice. `payload` é o corpo bruto, SEM dado de cartão.
 */
final readonly class EventoDoProvedor
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $provedor,
        public string $eventoId,
        public TipoEvento $tipo,
        public ?string $cobrancaId = null,
        public ?string $checkoutId = null,
        public ?string $referenciaInterna = null,
        public ?string $assinaturaId = null,
        public array $payload = [],
    ) {}
}
