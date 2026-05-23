<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Tipo de documento da Empresa Analisada (espec V2 §1.5.2).
 *
 * `cnpj` para PJ (caso comum) e `cpf` para autônomo não formalizado — o CPF
 * aqui é o da Empresa Analisada (≠ CPF do Usuário do Passo 1 do cadastro).
 */
enum TipoDocumento: string
{
    case Cnpj = 'cnpj';
    case Cpf = 'cpf';

    public function tamanho(): int
    {
        return match ($this) {
            self::Cnpj => 14,
            self::Cpf => 11,
        };
    }

    public function validar(string $documento): bool
    {
        return match ($this) {
            self::Cnpj => Cnpj::valido($documento),
            self::Cpf => Cpf::valido($documento),
        };
    }

    public function normalizar(string $documento): string
    {
        return match ($this) {
            self::Cnpj => Cnpj::normalizar($documento),
            self::Cpf => Cpf::normalizar($documento),
        };
    }
}
