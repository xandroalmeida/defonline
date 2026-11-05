<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/** STORY-073 — a foto da carteira de um Usuário num instante, entrada pura da {@see RegrasConsumo}. */
final readonly class SnapshotCarteira
{
    /** @param  list<LoteVisto>  $lotes */
    public function __construct(
        public CarbonImmutable $agora,
        public ?AssinaturaVista $assinatura,
        public array $lotes,
        public bool $trialElegivel,
        /** STORY-074 CA-2: a empresa em análise já recebeu análise de trial de outra conta (por CNPJ). */
        public bool $empresaJaEmTesteDeOutraConta = false,
    ) {}
}
