<?php

declare(strict_types=1);

namespace App\Domain\Rfb;

/**
 * Resultado tipado de uma consulta de CNPJ na RFB (STORY-015 CA-4).
 *
 * Vira `meta->>'status'` em `business_metrics` (tipo=`rfb_consulta`) e
 * eventualmente alimenta o alerta de taxa de erro >5% / 10 min (CA-5).
 *
 * Sucesso é o único caminho feliz; os demais valores cobrem as três classes
 * de falha que a abstração precisa distinguir para o monitoramento.
 */
enum RfbCnpjStatus: string
{
    case Sucesso = 'sucesso';
    case CnpjInexistente = 'cnpj_inexistente';
    case Timeout = 'timeout';
    case Erro5xx = 'erro_5xx';
    case ErroRede = 'erro_rede';

    public function ehSucesso(): bool
    {
        return $this === self::Sucesso;
    }

    public function ehErroDoProvedor(): bool
    {
        return match ($this) {
            self::Timeout, self::Erro5xx, self::ErroRede => true,
            self::Sucesso, self::CnpjInexistente => false,
        };
    }
}
