<?php

declare(strict_types=1);

use App\Domain\TipoDocumento;

it('CNPJ tem 14 dígitos e CPF tem 11', function () {
    expect(TipoDocumento::Cnpj->tamanho())->toBe(14);
    expect(TipoDocumento::Cpf->tamanho())->toBe(11);
});

it('valida documento conforme o tipo', function () {
    expect(TipoDocumento::Cnpj->validar('11.222.333/0001-81'))->toBeTrue();
    expect(TipoDocumento::Cnpj->validar('52998224725'))->toBeFalse();        // CPF não vale como CNPJ
    expect(TipoDocumento::Cpf->validar('529.982.247-25'))->toBeTrue();
    expect(TipoDocumento::Cpf->validar('11222333000181'))->toBeFalse();      // CNPJ não vale como CPF
});

it('normaliza documento removendo máscaras', function () {
    expect(TipoDocumento::Cnpj->normalizar('11.222.333/0001-81'))->toBe('11222333000181');
    expect(TipoDocumento::Cpf->normalizar('529.982.247-25'))->toBe('52998224725');
});
