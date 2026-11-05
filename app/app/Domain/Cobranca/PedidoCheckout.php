<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use InvalidArgumentException;

/**
 * Pedido de um checkout avulso. `referenciaInterna` é o id da nossa Invoice: volta em todo evento e
 * serve de chave de idempotência (ADR-010 §3, "idempotência de saída"). O valor é sempre o FINAL,
 * já com qualquer desconto aplicado por nós (ADR-010 4.7).
 */
final readonly class PedidoCheckout
{
    /** @param list<MeioPagamento> $meios */
    public function __construct(
        public string $referenciaInterna,
        public string $descricao,
        public int $valorCentavos,
        public array $meios,
        public int $expiraEmMinutos,
        public string $urlSucesso,
        public string $urlCancelamento,
        public string $urlExpirado,
    ) {
        if ($valorCentavos < 1) {
            throw new InvalidArgumentException('O valor do checkout deve ser positivo.');
        }

        if ($meios === []) {
            throw new InvalidArgumentException('Informe ao menos um meio de pagamento.');
        }

        if ($expiraEmMinutos < 10 || $expiraEmMinutos > 1440) {
            throw new InvalidArgumentException('A expiração do checkout deve estar entre 10 e 1440 minutos.');
        }
    }
}
