<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

use RuntimeException;

/** Webhook sem autenticação válida ou ilegível: responde 401 e nada é gravado. */
final class WebhookInvalido extends RuntimeException {}
