<?php

declare(strict_types=1);

namespace App\Services\Cobranca;

use App\Domain\Cobranca\ProvedorIndisponivel;
use RuntimeException;

/**
 * STORY-085 — o reembolso não foi feito por um motivo que o admin precisa ler (já solicitado, cobrança que
 * não está paga, provedor recusou). A mensagem é para a tela. Provedor fora do ar é outra exceção
 * ({@see ProvedorIndisponivel}): lá o resultado é desconhecido.
 */
final class ReembolsoNaoRealizado extends RuntimeException {}
