<?php

declare(strict_types=1);

use App\Domain\Cnpj;

it('aceita CNPJ válido formatado e cru', function (string $entrada) {
    expect(Cnpj::valido($entrada))->toBeTrue();
})->with([
    '11.222.333/0001-81',
    '11222333000181',
    '04.252.011/0001-10',
    '00.000.000/0001-91',                 // CNPJ matriz fictício mas com DV válido (raiz não trivial)
]);

it('rejeita CNPJ com dígito verificador errado', function () {
    expect(Cnpj::valido('11.222.333/0001-82'))->toBeFalse();
    expect(Cnpj::valido('11222333000180'))->toBeFalse();
});

it('rejeita CNPJ com tamanho diferente de 14 dígitos', function (string $entrada) {
    expect(Cnpj::valido($entrada))->toBeFalse();
})->with([
    '',
    '123',
    '1122233300018',           // 13 dígitos
    '112223330001811',         // 15 dígitos
    'abc.def.ghi/jklm-no',     // sem dígitos
]);

it('rejeita CNPJ com todos os dígitos iguais (sequência trivial)', function () {
    foreach (range(0, 9) as $d) {
        expect(Cnpj::valido(str_repeat((string) $d, 14)))->toBeFalse();
    }
});

it('normaliza para somente dígitos', function () {
    expect(Cnpj::normalizar('11.222.333/0001-81'))->toBe('11222333000181');
    expect(Cnpj::normalizar(' 11 222 333 0001 81 '))->toBe('11222333000181');
    expect(Cnpj::normalizar(''))->toBe('');
});

it('formata CNPJ de 14 dígitos no padrão XX.XXX.XXX/XXXX-XX', function () {
    expect(Cnpj::formatar('11222333000181'))->toBe('11.222.333/0001-81');
});

it('devolve a entrada quando não tem 14 dígitos para formatar', function () {
    expect(Cnpj::formatar('123'))->toBe('123');
    expect(Cnpj::formatar(''))->toBe('');
});
