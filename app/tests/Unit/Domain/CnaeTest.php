<?php

declare(strict_types=1);

use App\Domain\Cnae;

it('carrega a tabela de subclasses do IBGE com códigos de 7 dígitos', function () {
    $todas = Cnae::todas();

    expect(count($todas))->toBeGreaterThan(1000);
    foreach (array_keys($todas) as $codigo) {
        expect((string) $codigo)->toMatch('/^\d{7}$/');
    }
});

it('reconhece CNAE existente com ou sem pontuação', function (string $entrada) {
    expect(Cnae::existe($entrada))->toBeTrue()
        ->and(Cnae::descricao($entrada))->toBe('Fabricação de outros artigos de carpintaria para construção');
})->with(['1622699', '1622-6/99']);

it('rejeita CNAE inexistente', function () {
    expect(Cnae::existe('9999999'))->toBeFalse()
        ->and(Cnae::descricao('9999999'))->toBeNull();
});

it('formata no padrão oficial e preserva entrada fora do tamanho', function () {
    expect(Cnae::formatar('1622699'))->toBe('1622-6/99')
        ->and(Cnae::formatar('123'))->toBe('123');
});

it('converte a descrição do IBGE (caixa alta) para leitura', function () {
    expect(Cnae::descricaoLegivel('  FABRICAÇÃO DE  ÁLCOOL '))->toBe('Fabricação de álcool');
});
