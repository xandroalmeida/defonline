<?php

declare(strict_types=1);

namespace App\Support;

/** Telefone brasileiro para exibição e link de conversa no WhatsApp (DDD + 8 ou 9 dígitos). */
final class Telefone
{
    public static function formatar(?string $telefone): ?string
    {
        $d = self::digitos($telefone);

        return match (strlen($d)) {
            11 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7)),
            10 => sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6)),
            0 => null,
            default => $d,
        };
    }

    /** Link `wa.me` com o código do Brasil; nulo quando o número não tem DDD + 8 ou 9 dígitos. */
    public static function whatsapp(?string $telefone): ?string
    {
        $d = self::digitos($telefone);

        return in_array(strlen($d), [10, 11], true) ? 'https://wa.me/55'.$d : null;
    }

    private static function digitos(?string $telefone): string
    {
        return preg_replace('/\D+/', '', (string) $telefone) ?? '';
    }
}
