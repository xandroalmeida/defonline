<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** STORY-101 — o que o provedor diz sobre o cadastro do nosso webhook. */
final readonly class SituacaoDoWebhook
{
    public function __construct(
        public string $id,
        public bool $ativo,
        public bool $filaPausada,
        public bool $penalizado,
    ) {}
}
