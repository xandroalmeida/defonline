<?php

declare(strict_types=1);

namespace App\Services\Rfb;

use App\Domain\Rfb\RfbCnpjStatus;
use RuntimeException;
use Throwable;

/**
 * Falha tipada na consulta de CNPJ na RFB (STORY-015 CA-4).
 *
 * `RfbCnpjClient::consultarCnpj()` lança esta exceção em qualquer cenário
 * que não seja {@see RfbCnpjStatus::Sucesso}. A categoria viaja no enum
 * para alimentar `business_metrics.meta->>'status'` sem o handler ter que
 * interpretar `getMessage()` (mensagem é só humana).
 */
final class RfbCnpjFalhouException extends RuntimeException
{
    public function __construct(
        public readonly RfbCnpjStatus $status,
        public readonly string $provedor,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "Consulta RFB falhou: {$status->value}",
            0,
            $previous,
        );
    }
}
