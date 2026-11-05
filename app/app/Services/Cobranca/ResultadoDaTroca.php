<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\PreviaDeTroca;
use App\Models\Invoice;

/** STORY-082 — o que `TrocarDePlano::confirmar` fez: `aplicada` (vale agora), `agendada` (próximo ciclo) ou `checkout` (falta pagar a diferença). */
final readonly class ResultadoDaTroca
{
    public function __construct(
        public string $tipo,
        public PreviaDeTroca $previa,
        public ?Invoice $invoice = null,
    ) {}
}
