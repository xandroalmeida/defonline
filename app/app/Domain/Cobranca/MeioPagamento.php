<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** Meios aceitos no checkout. O cartão chega com a STORY-077/078. */
enum MeioPagamento: string
{
    case Pix = 'pix';
    case Cartao = 'cartao';
}
