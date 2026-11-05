<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\MotivoRecusaCupom;
use App\Models\Cupom;
use RuntimeException;

/** STORY-083 CA-3 — o cupom não pode ser usado nesta compra. `cupom` é nulo quando o código não existe. */
final class CupomRecusado extends RuntimeException
{
    public function __construct(public readonly MotivoRecusaCupom $motivo, public readonly ?Cupom $cupom = null)
    {
        parent::__construct("Cupom recusado: {$motivo->value}.");
    }
}
