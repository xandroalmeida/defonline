<?php

declare(strict_types=1);

use App\Domain\Cpf;

it('aceita CPF válido formatado e cru', function (string $entrada) {
    expect(Cpf::valido($entrada))->toBeTrue();
})->with([
    '529.982.247-25',
    '52998224725',
    '111.444.777-35',
]);

it('rejeita CPF com dígito verificador errado', function () {
    expect(Cpf::valido('529.982.247-26'))->toBeFalse();
    expect(Cpf::valido('11144477736'))->toBeFalse();
});

it('rejeita CPF com tamanho diferente de 11 dígitos', function (string $entrada) {
    expect(Cpf::valido($entrada))->toBeFalse();
})->with([
    '',
    '123',
    '1234567890',          // 10 dígitos
    '123456789012',        // 12 dígitos
    'abc.def.ghi-jk',      // sem dígitos
]);

it('rejeita CPFs com todos os dígitos iguais (sequência trivial)', function () {
    foreach (range(0, 9) as $d) {
        expect(Cpf::valido(str_repeat((string) $d, 11)))->toBeFalse();
    }
});

it('normaliza para somente dígitos', function () {
    expect(Cpf::normalizar('529.982.247-25'))->toBe('52998224725');
    expect(Cpf::normalizar(' 529 982 247 25 '))->toBe('52998224725');
    expect(Cpf::normalizar(''))->toBe('');
});

it('formata CPF de 11 dígitos no padrão XXX.XXX.XXX-XX', function () {
    expect(Cpf::formatar('52998224725'))->toBe('529.982.247-25');
});

it('devolve a entrada quando não tem 11 dígitos para formatar', function () {
    expect(Cpf::formatar('123'))->toBe('123');
});
