<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use RuntimeException;

/** O provedor não atendeu (rede, 5xx, recusa ou configuração inválida). A mensagem NUNCA traz corpo nem chave. */
final class ProvedorIndisponivel extends RuntimeException {}
