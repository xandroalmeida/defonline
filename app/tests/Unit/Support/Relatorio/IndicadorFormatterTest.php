<?php

declare(strict_types=1);

use App\Support\Relatorio\IndicadorFormatter;

it('devolve nome humanizado dos 15 indicadores conhecidos (14 do Anexo D + Ciclo Operacional)', function (string $codigo, string $nomeEsperado) {
    expect(IndicadorFormatter::nome($codigo))->toBe($nomeEsperado);
})->with([
    ['margem_bruta', 'Margem Bruta'],
    ['margem_ebitda', 'Margem EBITDA'],
    ['margem_liquida', 'Margem Líquida'],
    ['divida_liquida_ebitda', 'Dívida Líquida / EBITDA'],
    ['despesas_fin_ebitda', 'Despesas Financeiras / EBITDA'],
    ['fontes_recursos', 'Fontes de Recursos (PC / PL)'],
    ['giro_ativo', 'Giro do Ativo'],
    ['ciclo_financeiro', 'Ciclo Financeiro'],
    ['ncg_absoluto', 'NCG — Necessidade de Capital de Giro'],
    ['ncg_vendas', 'NCG / Vendas'],
    ['pmc', 'PMC — Prazo médio de pagamento'],
    ['pme', 'PME — Prazo médio de estoque'],
    ['pmr', 'PMR — Prazo médio de recebimento'],
    ['inadimplencia', 'Inadimplência'],
    ['ciclo_operacional', 'Ciclo Operacional'],
]);

it('devolve o próprio código quando o indicador não está no catálogo', function () {
    expect(IndicadorFormatter::nome('indicador_futuro_xyz'))->toBe('indicador_futuro_xyz');
});

it('formata valor null como travessão', function () {
    expect(IndicadorFormatter::valor('margem_bruta', null))->toBe('—');
});

it('formata margens como percentual com 1 casa e vírgula decimal BR', function () {
    expect(IndicadorFormatter::valor('margem_bruta', 50.0))->toBe('50,0%');
    expect(IndicadorFormatter::valor('margem_ebitda', 22.5))->toBe('22,5%');
    expect(IndicadorFormatter::valor('margem_liquida', 12.345))->toBe('12,3%');
    expect(IndicadorFormatter::valor('margem_liquida', -3.0))->toBe('-3,0%');
});

it('formata despesas_fin_ebitda e inadimplência como percentual com 1 casa', function () {
    expect(IndicadorFormatter::valor('despesas_fin_ebitda', 40.0))->toBe('40,0%');
    expect(IndicadorFormatter::valor('inadimplencia', 3.5))->toBe('3,5%');
});

it('formata Dívida Líq./EBITDA, Fontes Recursos e Giro como múltiplo com 2 casas', function () {
    expect(IndicadorFormatter::valor('divida_liquida_ebitda', 1.5))->toBe('1,50×');
    expect(IndicadorFormatter::valor('divida_liquida_ebitda', -0.04))->toBe('-0,04×');
    expect(IndicadorFormatter::valor('fontes_recursos', 0.5))->toBe('0,50×');
    expect(IndicadorFormatter::valor('giro_ativo', 2.5))->toBe('2,50×');
});

it('formata NCG/Vendas multiplicando razão por 100 (snapshot grava decimal)', function () {
    expect(IndicadorFormatter::valor('ncg_vendas', 0.085))->toBe('8,5%');
    expect(IndicadorFormatter::valor('ncg_vendas', -0.0416))->toBe('-4,2%');
});

it('formata prazos médios, ciclo financeiro e ciclo operacional em dias inteiros', function () {
    expect(IndicadorFormatter::valor('pmr', 45.0))->toBe('45 dias');
    expect(IndicadorFormatter::valor('pmc', 30))->toBe('30 dias');
    expect(IndicadorFormatter::valor('pme', 25.0))->toBe('25 dias');
    expect(IndicadorFormatter::valor('ciclo_financeiro', -60.0))->toBe('-60 dias');
    expect(IndicadorFormatter::valor('ciclo_operacional', 75.0))->toBe('75 dias');
});

it('formata NCG absoluto em moeda BR com sinal preservado', function () {
    expect(IndicadorFormatter::valor('ncg_absoluto', 50000.0))->toBe('R$ 50.000,00');
    expect(IndicadorFormatter::valor('ncg_absoluto', -50000.0))->toBe('-R$ 50.000,00');
    expect(IndicadorFormatter::valor('ncg_absoluto', 0.0))->toBe('R$ 0,00');
});

it('formata indicador desconhecido como número BR com 2 casas (fallback)', function () {
    expect(IndicadorFormatter::valor('indicador_futuro', 123.456))->toBe('123,46');
});

it('expõe a ordem canônica dos 13 essenciais (com farol) — alinhada ao Anexo D', function () {
    expect(IndicadorFormatter::ORDEM_ESSENCIAIS)->toBe([
        'margem_bruta',
        'margem_ebitda',
        'margem_liquida',
        'divida_liquida_ebitda',
        'despesas_fin_ebitda',
        'fontes_recursos',
        'giro_ativo',
        'ciclo_financeiro',
        'ncg_vendas',
        'pmc',
        'pme',
        'pmr',
        'inadimplencia',
    ]);
});
