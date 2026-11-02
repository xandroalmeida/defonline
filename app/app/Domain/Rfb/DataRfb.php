<?php

declare(strict_types=1);

namespace App\Domain\Rfb;

/**
 * Datas da ficha RFB circulam como string ISO `Y-m-d` — o domínio fica livre
 * de fuso e de Carbon. Os provedores mandam ISO (cnpja), `dd/mm/aaaa`
 * (receitaws) ou timestamp ISO completo.
 */
final class DataRfb
{
    public static function normalizar(mixed $bruto): ?string
    {
        if (! is_string($bruto)) {
            return null;
        }

        $bruto = trim($bruto);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $bruto, $m) === 1) {
            [$ano, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $bruto, $m) === 1) {
            [$dia, $mes, $ano] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        if (! checkdate($mes, $dia, $ano)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
    }

    /** `2005-11-03` → `03/11/2005`. */
    public static function formatar(?string $iso): ?string
    {
        $iso = self::normalizar($iso);
        if ($iso === null) {
            return null;
        }

        [$ano, $mes, $dia] = explode('-', $iso);

        return "{$dia}/{$mes}/{$ano}";
    }
}
