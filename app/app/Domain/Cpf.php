<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * CPF — validação e normalização pura (sem dependências, sem framework).
 *
 * Vive em `app/Domain` por ser regra pura (princípio #5 do Programador) e por
 * disparar o gate de cobertura ≥98% do `phpunit-domain.xml` (STORY-010).
 *
 * Normalização: remove qualquer caractere não-dígito. Banco armazena 11 dígitos.
 * Validação: calcula os dois dígitos verificadores conforme algoritmo da RFB.
 *
 * Referência: especificação V2 §1.5.2 (Usuário = pessoa física identificada por CPF).
 */
final class Cpf
{
    public static function normalizar(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf) ?? '';
    }

    public static function valido(string $cpf): bool
    {
        $digitos = self::normalizar($cpf);

        if (strlen($digitos) !== 11) {
            return false;
        }

        // Sequências triviais (000…000, 111…111 etc.) são rejeitadas pela RFB.
        if (preg_match('/^(\d)\1{10}$/', $digitos) === 1) {
            return false;
        }

        return self::dvCorreto($digitos, 9) && self::dvCorreto($digitos, 10);
    }

    public static function formatar(string $cpf): string
    {
        $d = self::normalizar($cpf);

        if (strlen($d) !== 11) {
            return $cpf;
        }

        return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2);
    }

    private static function dvCorreto(string $digitos, int $tamanho): bool
    {
        $soma = 0;
        $peso = $tamanho + 1;
        for ($i = 0; $i < $tamanho; $i++) {
            $soma += ((int) $digitos[$i]) * $peso;
            $peso--;
        }
        $resto = $soma % 11;
        $dv = $resto < 2 ? 0 : 11 - $resto;

        return ((int) $digitos[$tamanho]) === $dv;
    }
}
