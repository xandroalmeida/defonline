<?php

declare(strict_types=1);

namespace App\Domain\Cobranca;

/**
 * STORY-074 — CPF e CNPJ dos controles do trial guardados só como HMAC-SHA256 (chave da aplicação):
 * um hash simples de 11 ou 14 dígitos se desfaz por força bruta, o HMAC não.
 */
final class DocumentoHash
{
    public static function de(string $documento): string
    {
        $digitos = preg_replace('/\D+/', '', $documento) ?? '';

        return hash_hmac('sha256', $digitos, (string) config('app.key'));
    }
}
