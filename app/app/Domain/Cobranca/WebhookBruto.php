<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** Requisição HTTP de webhook, sem tipo do framework: cabeçalhos em minúsculas e corpo cru. */
final readonly class WebhookBruto
{
    /** @param array<string, string> $cabecalhos */
    public function __construct(
        public array $cabecalhos,
        public string $corpo,
    ) {}

    public function cabecalho(string $nome): ?string
    {
        return $this->cabecalhos[strtolower($nome)] ?? null;
    }
}
