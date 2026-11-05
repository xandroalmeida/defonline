<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** STORY-073 CA-8 — de onde saiu o saldo de uma análise. */
enum Origem: string
{
    case Cota = 'cota';
    case Credito = 'credito';
}
