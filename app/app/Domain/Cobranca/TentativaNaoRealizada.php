<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use RuntimeException;

/**
 * STORY-079 — o provedor RESPONDEU, mas recusou o pedido de nova tentativa (por exemplo, cobrança que
 * ele não deixa reagendar). Diferente de {@see ProvedorIndisponivel}: o provedor está de pé, só não
 * cobrou. A régua conta como tentativa sem sucesso, porque tentar de novo agora não adiantaria.
 */
final class TentativaNaoRealizada extends RuntimeException {}
