<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use Carbon\CarbonImmutable;

/**
 * STORY-082 — o que a troca de plano vai fazer, calculado antes de o usuário confirmar (CA-1).
 *
 * - `imediata`: vale agora (upgrade); `false` = agendada para o fim do ciclo (downgrade).
 * - `mesmoCiclo`: o período em curso continua (só a diferença é cobrada); senão um período novo começa hoje.
 * - `aPagarCentavos`: nunca negativo (CA-3). `semCobranca`: diferença abaixo do mínimo, aplicada sem cobrar.
 */
final readonly class PreviaDeTroca
{
    public function __construct(
        public bool $imediata,
        public bool $mesmoCiclo,
        public int $precoNovoCentavos,
        public int $precoAtualCentavos,
        /** Parcela do preço NOVO que cobre o que resta do período (igual ao preço cheio quando um período novo começa). */
        public int $parcelaNovaCentavos,
        /** Crédito do plano atual pelo que resta do período, já descontado. */
        public int $creditoCentavos,
        public int $aPagarCentavos,
        public bool $semCobranca,
        public int $diasRestantes,
        public CarbonImmutable $vigenteAte,
        public CarbonImmutable $proximaCobranca,
        public CicloCobranca $cicloNovo,
        public int $cotaNova,
    ) {}
}
