<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * CNPJ — validação e normalização pura (sem dependências, sem framework).
 *
 * Vive em `app/Domain` por ser regra pura (princípio #5 do Programador) e por
 * disparar o gate de cobertura ≥98% do `phpunit-domain.xml` (STORY-010).
 *
 * Normalização: remove qualquer caractere não-dígito. Banco armazena 14 dígitos.
 * Validação: calcula os dois dígitos verificadores conforme algoritmo da RFB.
 *
 * Referência: especificação V2 §1.5.2 (Empresa Analisada PJ identificada por CNPJ).
 */
final class Cnpj
{
    /** Pesos do 1º DV (12 dígitos da base). */
    private const PESOS_DV1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /** Pesos do 2º DV (13 dígitos: base + 1º DV). */
    private const PESOS_DV2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    public static function normalizar(string $cnpj): string
    {
        return preg_replace('/\D+/', '', $cnpj) ?? '';
    }

    public static function valido(string $cnpj): bool
    {
        $digitos = self::normalizar($cnpj);

        if (strlen($digitos) !== 14) {
            return false;
        }

        // Sequências triviais (00…00, 11…11 etc.) são rejeitadas: matematicamente o DV
        // bate, mas a RFB nunca emitiu CNPJ nessas configurações.
        if (preg_match('/^(\d)\1{13}$/', $digitos) === 1) {
            return false;
        }

        return self::dvCorreto($digitos, self::PESOS_DV1, 12)
            && self::dvCorreto($digitos, self::PESOS_DV2, 13);
    }

    public static function formatar(string $cnpj): string
    {
        $d = self::normalizar($cnpj);

        if (strlen($d) !== 14) {
            return $cnpj;
        }

        return substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12, 2);
    }

    /**
     * @param  list<int>  $pesos
     */
    private static function dvCorreto(string $digitos, array $pesos, int $posicao): bool
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += ((int) $digitos[$i]) * $peso;
        }
        $resto = $soma % 11;
        $dv = $resto < 2 ? 0 : 11 - $resto;

        return ((int) $digitos[$posicao]) === $dv;
    }
}
