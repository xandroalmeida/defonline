<?php

declare(strict_types=1);

namespace App\Support\Relatorio;

/**
 * Diferenças de apresentação entre versões do motor. O snapshot de um diagnóstico
 * é imutável (IDR-010), então o relatório precisa exibi-lo como ele foi calculado.
 */
final class MotorLegado
{
    /**
     * Antes do motor 2.0.0, o NCG/Vendas foi gravado como fração das vendas anuais
     * (PDR-014). Sem versão informada, vale a regra atual.
     */
    public static function ncgSobreVendasAnuais(?string $motorVersion): bool
    {
        return $motorVersion !== null && version_compare($motorVersion, '2.0.0', '<');
    }
}
