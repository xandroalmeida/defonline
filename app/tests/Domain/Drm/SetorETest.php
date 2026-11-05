<?php

declare(strict_types=1);

use App\Domain\Drm\Competencia;
use App\Domain\Drm\Dinheiro;
use App\Domain\Drm\Grupo;
use App\Domain\Drm\Modo;
use App\Domain\Drm\Setor;
use Carbon\CarbonImmutable;

/*
|-------------------------------------------------------------------------
| STORY-047 CA-3 / CA-7 — estrutura por setor, competência e dinheiro.
|-------------------------------------------------------------------------
*/

it('deriva o setor da divisão do CNAE', function (?string $cnae, Setor $esperado) {
    expect(Setor::doCnae($cnae))->toBe($esperado);
})->with([
    'indústria de alimentos' => ['1091101', Setor::Industria],
    'limite inferior da indústria' => ['0500000', Setor::Industria],
    'limite superior da indústria' => ['3399999', Setor::Industria],
    'comércio varejista' => ['4711301', Setor::Comercio],
    'comércio atacadista' => ['4623199', Setor::Comercio],
    'serviço de contabilidade' => ['6920601', Setor::Servicos],
    'eletricidade é serviço' => ['3511500', Setor::Servicos],
    'transporte é serviço' => ['4930201', Setor::Servicos],
    'sem CNAE usa o setor sem supressão' => [null, Setor::Industria],
    'CNAE curto demais' => ['4', Setor::Industria],
    'CNAE não numérico' => ['ab12345', Setor::Industria],
]);

it('suprime D04 e D05 conforme o setor (Anexo K §K.4)', function () {
    expect(Setor::Industria->camposSuprimidos())->toBe([])
        ->and(Setor::Comercio->camposSuprimidos())->toBe(['D05'])
        ->and(Setor::Servicos->camposSuprimidos())->toBe(['D04', 'D05']);
});

it('conta os campos do seletor depois da supressão por setor (DDR-002)', function () {
    expect(Grupo::Custo->custoEmCampos(Setor::Industria, Modo::Detalhado))->toBe(4)
        ->and(Grupo::Custo->custoEmCampos(Setor::Comercio, Modo::Detalhado))->toBe(3)
        ->and(Grupo::Custo->custoEmCampos(Setor::Servicos, Modo::Detalhado))->toBe(2)
        ->and(Grupo::Custo->custoEmCampos(Setor::Servicos, Modo::Agregado))->toBe(1)
        ->and(Grupo::Vendas->custoEmCampos(Setor::Comercio, Modo::Detalhado))->toBe(6)
        ->and(Grupo::Administrativas->custoEmCampos(Setor::Comercio, Modo::Detalhado))->toBe(21)
        ->and(Grupo::Financeiras->custoEmCampos(Setor::Comercio, Modo::Detalhado))->toBe(2);
});

it('descobre o grupo de um campo', function () {
    expect(Grupo::doCampo('D07'))->toBe(Grupo::Custo)
        ->and(Grupo::doCampo('D15'))->toBe(Grupo::Administrativas)
        ->and(Grupo::doCampo('D39'))->toBe(Grupo::Financeiras)
        ->and(Grupo::doCampo('D01'))->toBeNull()
        ->and(Grupo::doCampo('D40'))->toBeNull();
});

describe('Competencia', function () {
    $hoje = CarbonImmutable::parse('2026-11-05');

    it('sugere o mês anterior ao corrente', function () use ($hoje) {
        expect(Competencia::padrao($hoje)->texto())->toBe('2026-10')
            ->and(Competencia::padrao(CarbonImmutable::parse('2026-01-31'))->texto())->toBe('2025-12');
    });

    it('bloqueia o mês corrente e o futuro, mas não o passado (B2)', function () use ($hoje) {
        expect(Competencia::deTexto('2026-11')->ehFutura($hoje))->toBeTrue()
            ->and(Competencia::deTexto('2027-01')->ehFutura($hoje))->toBeTrue()
            ->and(Competencia::deTexto('2026-10')->ehFutura($hoje))->toBeFalse()
            ->and(Competencia::deTexto('2020-01')->ehFutura($hoje))->toBeFalse();
    });

    it('formata para banco e para a tela', function () {
        $c = Competencia::deTexto('2026-03');

        expect($c->dia01())->toBe('2026-03-01')
            ->and($c->rotulo())->toBe('março/2026')
            ->and($c->mesPorExtenso())->toBe('março')
            ->and(Competencia::deData(new DateTime('2026-08-17'))->texto())->toBe('2026-08');
    });

    it('rejeita texto fora de AAAA-MM', function (string $texto) {
        expect(fn () => Competencia::deTexto($texto))->toThrow(InvalidArgumentException::class);
    })->with(['2026-13', '2026-00', '26-05', 'agosto', '', '2026-5', '2026-05-01']);

    it('lista os 24 últimos meses fechados, do mais recente ao mais antigo', function () use ($hoje) {
        $meses = Competencia::ultimosFechados($hoje);

        expect($meses)->toHaveCount(24)
            ->and($meses[0]->texto())->toBe('2026-10')
            ->and($meses[23]->texto())->toBe('2024-11');
    });
});

describe('Dinheiro', function () {
    it('interpreta os formatos aceitos em centavos', function (string $texto, int $centavos) {
        expect(Dinheiro::interpretar($texto))->toBe([Dinheiro::OK, $centavos]);
    })->with([
        'máscara' => ['1.234,56', 123456],
        'sem milhar' => ['1234,56', 123456],
        'ponto decimal' => ['1234.56', 123456],
        'uma casa' => ['1234.5', 123450],
        'inteiro' => ['1234', 123400],
        'milhar sem vírgula' => ['1.234', 123400],
        'zero' => ['0,00', 0],
        'menos zero' => ['-0,00', 0],
        'espaços' => ['  10,5  ', 1050],
        'teto' => ['999.999.999,99', 99_999_999_999],
    ]);

    it('distingue vazio, negativo e inválido', function () {
        expect(Dinheiro::interpretar(null))->toBe([Dinheiro::VAZIO, null])
            ->and(Dinheiro::interpretar('   '))->toBe([Dinheiro::VAZIO, null])
            ->and(Dinheiro::interpretar('-10,00'))->toBe([Dinheiro::NEGATIVO, null])
            ->and(Dinheiro::interpretar('abc'))->toBe([Dinheiro::INVALIDO, null])
            ->and(Dinheiro::interpretar('1,234,56'))->toBe([Dinheiro::INVALIDO, null])
            ->and(Dinheiro::interpretar('10,999'))->toBe([Dinheiro::INVALIDO, null])
            ->and(Dinheiro::interpretar('1.000.000.000,00'))->toBe([Dinheiro::INVALIDO, null]);
    });

    it('formata de volta sem perder centavo', function () {
        expect(Dinheiro::decimal(123456))->toBe('1234.56')
            ->and(Dinheiro::decimal(5))->toBe('0.05')
            ->and(Dinheiro::decimal(0))->toBe('0.00')
            ->and(Dinheiro::deDecimal('1234.56'))->toBe(123456)
            ->and(Dinheiro::deDecimal('10'))->toBe(1000)
            ->and(Dinheiro::mascara(123456))->toBe('1.234,56')
            ->and(Dinheiro::brl(99))->toBe('R$ 0,99');
    });
});
