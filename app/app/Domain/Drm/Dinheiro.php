<?php

declare(strict_types=1);

namespace App\Domain\Drm;

/**
 * Valores em reais como centavos inteiros — aritmética exata, sem float.
 *
 * A entrada é o texto que a máscara BRL produz ("1.234,56"), mas também aceita
 * o que chega por colagem ou direto no servidor ("1234,56", "1234.56", "-5").
 */
final class Dinheiro
{
    public const VAZIO = 'vazio';

    public const OK = 'ok';

    public const NEGATIVO = 'negativo';

    public const INVALIDO = 'invalido';

    /** Teto defensivo: 999.999.999,99. Acima disso é erro de digitação ou abuso. */
    public const MAXIMO_CENTAVOS = 99_999_999_999;

    /**
     * @return array{0: string, 1: int|null} Estado e, se `ok`, os centavos.
     */
    public static function interpretar(?string $texto): array
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            return [self::VAZIO, null];
        }

        $negativo = str_starts_with($texto, '-');
        $corpo = ltrim($texto, '-');

        // Com vírgula: pontos são milhar. Sem vírgula: ponto é decimal só se tiver 1-2 casas depois.
        if (str_contains($corpo, ',')) {
            $corpo = str_replace('.', '', $corpo);
            $corpo = str_replace(',', '.', $corpo);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $corpo) === 1) {
            $corpo = str_replace('.', '', $corpo);
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $corpo) !== 1) {
            return [self::INVALIDO, null];
        }

        [$inteiro, $fracao] = array_pad(explode('.', $corpo), 2, '');
        if (strlen(ltrim($inteiro, '0')) > 9) {
            return [self::INVALIDO, null];
        }
        $centavos = (int) $inteiro * 100 + (int) str_pad($fracao, 2, '0');

        if ($negativo && $centavos > 0) {
            return [self::NEGATIVO, null];
        }

        return [self::OK, $centavos];
    }

    /** `1234.56` — forma canônica do payload e do hash. */
    public static function decimal(int $centavos): string
    {
        return intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function deDecimal(string $decimal): int
    {
        [$inteiro, $fracao] = array_pad(explode('.', $decimal), 2, '0');

        return (int) $inteiro * 100 + (int) str_pad(substr($fracao, 0, 2), 2, '0');
    }

    /** `1.234,56` — o que o campo mascarado exibe. */
    public static function mascara(int $centavos): string
    {
        return number_format($centavos / 100, 2, ',', '.');
    }

    /** `R$ 1.234,56`. */
    public static function brl(int $centavos): string
    {
        return 'R$ '.self::mascara($centavos);
    }
}
