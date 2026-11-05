<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** STORY-073 — resultado da verificação de saldo: de onde sai a análise, ou por que está bloqueada. */
final readonly class Decisao
{
    private function __construct(
        public ?Origem $origem,
        public ?string $assinaturaId,
        public ?string $loteId,
        public ?Bloqueio $bloqueio,
    ) {}

    public static function cota(string $assinaturaId): self
    {
        return new self(Origem::Cota, $assinaturaId, null, null);
    }

    public static function credito(string $loteId): self
    {
        return new self(Origem::Credito, null, $loteId, null);
    }

    public static function bloquear(Bloqueio $bloqueio): self
    {
        return new self(null, null, null, $bloqueio);
    }

    public function permitido(): bool
    {
        return $this->bloqueio === null;
    }
}
