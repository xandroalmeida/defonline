<?php

declare(strict_types=1);

use App\Domain\Rfb\Atividade;
use App\Domain\Rfb\DataRfb;
use App\Domain\Rfb\Endereco;
use App\Domain\Rfb\FichaRfb;
use App\Domain\Rfb\OpcaoRegime;
use App\Domain\Rfb\Socio;

/**
 * STORY-063 — objetos de valor da ficha RFB (núcleo, ≥98%).
 */
describe('DataRfb', function () {
    it('normaliza ISO, ISO com hora e dd/mm/aaaa para Y-m-d', function (mixed $bruto, ?string $esperado) {
        expect(DataRfb::normalizar($bruto))->toBe($esperado);
    })->with([
        'iso' => ['2005-11-03', '2005-11-03'],
        'iso com hora' => ['2026-10-29T00:13:53.764Z', '2026-10-29'],
        'br' => ['03/11/2005', '2005-11-03'],
        'br com espaços' => [' 03/11/2005 ', '2005-11-03'],
        'data impossível' => ['31/02/2020', null],
        'formato desconhecido' => ['2005.11.03', null],
        'vazia' => ['', null],
        'null' => [null, null],
        'número' => [20051103, null],
    ]);

    it('formata para dd/mm/aaaa e devolve null quando inválida', function () {
        expect(DataRfb::formatar('2005-11-03'))->toBe('03/11/2005');
        expect(DataRfb::formatar(null))->toBeNull();
        expect(DataRfb::formatar('lixo'))->toBeNull();
    });
});

describe('Atividade', function () {
    it('normaliza código com pontuação, inteiro e com zero à esquerda', function (mixed $bruto, ?string $esperado) {
        expect(Atividade::normalizarCodigo($bruto))->toBe($esperado);
    })->with([
        'pontuado' => ['64.22-1-00', '6422100'],
        'inteiro' => [6422100, '6422100'],
        'seis dígitos' => [111301, '0111301'],
        'sem atividade receitaws' => ['00.00-0-00', null],
        'vazio' => ['', null],
        'array' => [['x'], null],
        'null' => [null, null],
    ]);

    it('cria com descrição aparada e devolve null sem código', function () {
        $a = Atividade::deBruto('47.21-1-02', '  Padaria  ');
        expect($a?->codigo)->toBe('4721102');
        expect($a?->descricao)->toBe('Padaria');
        expect($a?->codigoFormatado())->toBe('4721-1/02');
        expect(Atividade::deBruto('4721102', '   ')?->descricao)->toBeNull();
        expect(Atividade::deBruto('4721102', 123)?->descricao)->toBeNull();
        expect(Atividade::deBruto(null, 'x'))->toBeNull();
    });

    it('faz round-trip por array', function () {
        $a = new Atividade('4721102', 'Padaria');
        expect(Atividade::fromArray($a->toArray()))->toEqual($a);
        expect(Atividade::fromArray([]))->toBeNull();
    });
});

describe('Endereco', function () {
    it('limpa strings vazias, só-dígitos no CEP e UF maiúscula', function () {
        $e = Endereco::deBruto([
            'logradouro' => ' Rua A ',
            'numero' => 10,
            'complemento' => '',
            'bairro' => ['inesperado'],
            'cep' => '70.040-912',
            'municipio' => 'Brasília',
            'uf' => 'df',
        ]);

        expect($e->logradouro)->toBe('Rua A');
        expect($e->numero)->toBe('10');
        expect($e->complemento)->toBeNull();
        expect($e->bairro)->toBeNull();
        expect($e->cep)->toBe('70040912');
        expect($e->cepFormatado())->toBe('70040-912');
        expect($e->uf)->toBe('DF');
    });

    it('CEP sem dígitos vira null; CEP fora do padrão sai como veio', function () {
        expect(Endereco::deBruto(['cep' => '---'])->cep)->toBeNull();
        expect(Endereco::deBruto(['cep' => '123'])->cepFormatado())->toBe('123');
        expect((new Endereco)->cepFormatado())->toBeNull();
        expect(Endereco::deBruto([])->uf)->toBeNull();
    });

    it('faz round-trip por array', function () {
        $e = new Endereco('Rua A', '1', 'Sala 2', 'Centro', '01001000', 'São Paulo', 'SP');
        expect(Endereco::fromArray($e->toArray()))->toEqual($e);
    });
});

describe('Socio', function () {
    it('remove o código numérico da qualificação e normaliza a data de entrada', function () {
        $s = Socio::deBruto(' Fulana ', '49-Sócio-Administrador', '***123456**', '12/06/2023');
        expect($s?->nome)->toBe('Fulana');
        expect($s?->qualificacao)->toBe('Sócio-Administrador');
        expect($s?->documento)->toBe('***123456**');
        expect($s?->dataEntrada)->toBe('2023-06-12');
    });

    it('sem nome não é sócio; opcionais ausentes viram null', function () {
        expect(Socio::deBruto(''))->toBeNull();
        expect(Socio::deBruto(null))->toBeNull();
        $s = Socio::deBruto('Fulana', '', null, 'x');
        expect($s?->qualificacao)->toBeNull();
        expect($s?->documento)->toBeNull();
        expect($s?->dataEntrada)->toBeNull();
    });

    it('faz round-trip por array', function () {
        $s = new Socio('Fulana', 'Diretor', '***123456**', '2023-06-12');
        expect(Socio::fromArray($s->toArray()))->toEqual($s);
        expect(Socio::fromArray([]))->toBeNull();
    });
});

describe('OpcaoRegime', function () {
    it('só aceita booleano como optante', function () {
        expect(OpcaoRegime::deBruto(true, '01/07/2007')->toArray())
            ->toBe(['optante' => true, 'data_opcao' => '2007-07-01', 'data_exclusao' => null]);
        expect(OpcaoRegime::deBruto('S')->optante)->toBeNull();
    });

    it('rotula optante, não optante e não informado', function () {
        expect((new OpcaoRegime(true))->rotulo())->toBe('Optante');
        expect((new OpcaoRegime(false))->rotulo())->toBe('Não optante');
        expect((new OpcaoRegime)->rotulo())->toBeNull();
    });

    it('faz round-trip por array', function () {
        $o = new OpcaoRegime(false, '2007-07-01', '2012-12-31');
        expect(OpcaoRegime::fromArray($o->toArray()))->toEqual($o);
    });
});

describe('FichaRfb', function () {
    it('normaliza capital social de int, float e string numérica', function (mixed $bruto, ?string $esperado) {
        expect(FichaRfb::normalizarCapital($bruto))->toBe($esperado);
    })->with([
        'int' => [120000000000, '120000000000.00'],
        'float' => [1500.5, '1500.50'],
        'string' => ['1000.00', '1000.00'],
        'string não numérica' => ['mil', null],
        'null' => [null, null],
    ]);

    it('formata capital social em reais', function () {
        expect((new FichaRfb(capitalSocial: '120000000000.00'))->capitalSocialFormatado())->toBe('R$ 120.000.000.000,00');
        expect((new FichaRfb)->capitalSocialFormatado())->toBeNull();
    });

    it('faz round-trip completo por array', function () {
        $ficha = new FichaRfb(
            naturezaJuridica: 'Sociedade Empresária Limitada',
            porte: 'Microempresa',
            capitalSocial: '10000.00',
            dataSituacao: '2020-01-01',
            cnaePrincipal: new Atividade('4721102', 'Padaria'),
            cnaesSecundarios: [new Atividade('4724500', 'Hortifruti'), new Atividade('1091102')],
            endereco: new Endereco('Rua A', '1', null, 'Centro', '01001000', 'São Paulo', 'SP'),
            socios: [new Socio('Fulana', 'Sócio-Administrador')],
            simples: new OpcaoRegime(true, '2020-01-01'),
            simei: new OpcaoRegime(false),
        );

        expect(FichaRfb::fromArray($ficha->toArray()))->toEqual($ficha);
    });

    it('fromArray tolera array vazio e entradas malformadas', function () {
        $ficha = FichaRfb::fromArray([
            'natureza_juridica' => '',
            'cnae_principal' => 'x',
            'cnaes_secundarios' => [['codigo' => '4721102'], 'lixo', ['codigo' => null]],
            'socios' => 'lixo',
            'endereco' => 'lixo',
            'simples' => null,
        ]);

        expect($ficha->naturezaJuridica)->toBeNull();
        expect($ficha->cnaePrincipal)->toBeNull();
        expect($ficha->cnaesSecundarios)->toHaveCount(1);
        expect($ficha->socios)->toBe([]);
        expect($ficha->endereco)->toEqual(new Endereco);
        expect($ficha->simples)->toEqual(new OpcaoRegime);
        expect(FichaRfb::fromArray([]))->toEqual(new FichaRfb);
    });
});
