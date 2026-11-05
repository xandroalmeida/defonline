<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/** STORY-079 — o que a régua de cobrança manda fazer com uma assinatura em atraso agora. */
enum AcaoDaRegua: string
{
    /** Ainda não é hora da próxima tentativa. */
    case Aguardar = 'aguardar';
    case Retentar = 'retentar';
    /** As tentativas acabaram e o prazo da última passou sem pagamento. */
    case Encerrar = 'encerrar';
}
