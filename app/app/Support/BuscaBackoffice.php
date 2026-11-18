<?php

declare(strict_types=1);

namespace App\Support;

/**
 * STORY-104 — termo de busca do backoffice: texto para nome/e-mail e, quando há dígitos suficientes,
 * os dígitos para CPF, CNPJ e telefone (aceita com ou sem máscara). A auditoria guarda o termo, mas
 * com os dígitos mascarados: buscar por CPF não pode deixar o CPF de um cliente no log.
 */
final class BuscaBackoffice
{
    /** Menos que isso casaria com quase todo documento. */
    public const MIN_DIGITOS = 4;

    public readonly string $texto;

    public readonly string $padrao;

    public readonly ?string $digitos;

    public function __construct(string $termo)
    {
        $this->texto = trim($termo);
        $this->padrao = '%'.addcslashes($this->texto, '%_\\').'%';
        $d = preg_replace('/\D+/', '', $this->texto) ?? '';
        $this->digitos = strlen($d) >= self::MIN_DIGITOS ? $d : null;
    }

    public function vazia(): bool
    {
        return $this->texto === '';
    }

    public function paraAuditoria(): ?string
    {
        if ($this->vazia()) {
            return null;
        }

        return $this->digitos === null ? $this->texto : (preg_replace('/\d/', '*', $this->texto) ?? '');
    }
}
