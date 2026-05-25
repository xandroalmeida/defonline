<?php

declare(strict_types=1);

use App\Domain\Motor\Calculos\DreAdaptada;

/*
| ----------------------------------------------------------------------
| DRE Adaptada — agregados anualizados + balanço (espec V2.5 §4.5 §Anexos B/C).
|
| Cobre as três famílias de saída:
|   1. Balanço (Q02..Q07, Ativo Total, PC, PL).
|   2. DRE (Vendas, Compras, LB, Despesas anuais, EBITDA, LOL).
|   3. Helpers de resiliência (faltante → null em cascata).
| ----------------------------------------------------------------------
*/

function fp(array $overrides = []): array
{
    return array_merge([
        'Q02' => '50000',
        'Q03' => '80000',
        'Q04' => '60000',
        'Q05' => '300000',
        'Q06' => '40000',
        'Q07' => '30000',
        'Q08' => '50000',
        'Q09' => '100000',
        'Q14' => '20000',
        'Q15' => '8000',
        'Q16' => '2000',
    ], $overrides);
}

it('anualiza Q09 (vendas mensais x 12)', function () {
    $dre = new DreAdaptada(fp(['Q09' => '100000']));
    expect($dre->vendasAnuais())->toBe('1200000.0000');
});

it('anualiza Q08 (compras mensais x 12)', function () {
    $dre = new DreAdaptada(fp(['Q08' => '50000']));
    expect($dre->comprasAnuais())->toBe('600000.0000');
});

it('calcula Lucro Bruto = Vendas − Compras', function () {
    $dre = new DreAdaptada(fp(['Q09' => '100000', 'Q08' => '50000']));
    expect($dre->lucroBruto())->toBe('600000.0000');
});

it('calcula EBITDA = LB − Despesas fixas anuais − Despesas variáveis anuais', function () {
    // LB = (100000 − 50000) × 12 = 600000
    // DF = 20000 × 12 = 240000
    // DV = 8000 × 12 = 96000
    // EBITDA = 600000 − 240000 − 96000 = 264000
    $dre = new DreAdaptada(fp());
    expect($dre->ebitda())->toBe('264000.0000');
});

it('calcula LOL = EBITDA − Despesas Financeiras anuais', function () {
    // EBITDA = 264000; DespFin = 2000 × 12 = 24000; LOL = 240000.
    $dre = new DreAdaptada(fp());
    expect($dre->lucroOperacionalLiquido())->toBe('240000.0000');
});

it('calcula Ativo Total = Q02 + Q03 + Q04 + Q05', function () {
    // 50000 + 80000 + 60000 + 300000 = 490000
    $dre = new DreAdaptada(fp());
    expect($dre->ativoTotal())->toBe('490000.0000');
});

it('calcula Passivo Circulante = Q06 + Q07', function () {
    // 40000 + 30000 = 70000
    $dre = new DreAdaptada(fp());
    expect($dre->passivoCirculante())->toBe('70000.0000');
});

it('calcula Patrimônio Líquido = AT − PC (simplificação Anexo B)', function () {
    // AT = 490000; PC = 70000; PL = 420000.
    $dre = new DreAdaptada(fp());
    expect($dre->patrimonioLiquido())->toBe('420000.0000');
});

// ---------------------------------------------------------------
// Resiliência a faltantes — propaga null em vez de exception.
// ---------------------------------------------------------------

it('retorna null em Vendas quando Q09 ausente', function () {
    $dre = new DreAdaptada(fp(['Q09' => null]));
    expect($dre->vendasAnuais())->toBeNull();
});

it('retorna null em LB quando Compras faltam', function () {
    $dre = new DreAdaptada(fp(['Q08' => null]));
    expect($dre->lucroBruto())->toBeNull();
});

it('retorna null em EBITDA quando Q14 ausente', function () {
    $dre = new DreAdaptada(fp(['Q14' => null]));
    expect($dre->ebitda())->toBeNull();
});

it('retorna null em LOL quando Q16 ausente (embora EBITDA seja calculável)', function () {
    $dre = new DreAdaptada(fp(['Q16' => null]));
    expect($dre->ebitda())->not->toBeNull();
    expect($dre->lucroOperacionalLiquido())->toBeNull();
});

it('retorna null em Ativo Total quando qualquer componente ausente', function () {
    $dre = new DreAdaptada(fp(['Q04' => null]));
    expect($dre->ativoTotal())->toBeNull();
    expect($dre->patrimonioLiquido())->toBeNull();
});

it('trata string vazia como null (defensa em profundidade — canonicalizer já normaliza)', function () {
    $dre = new DreAdaptada(fp(['Q09' => '']));
    expect($dre->vendasAnuais())->toBeNull();
});

it('aceita valores decimais com casas (não força casas)', function () {
    $dre = new DreAdaptada(fp(['Q09' => '100000.50']));
    expect($dre->vendasAnuais())->toBe('1200006.0000'); // 100000.50 × 12 = 1200006.
});

it('aceita valores como int ou float (não exige string)', function () {
    $dreInt = new DreAdaptada(fp(['Q09' => 100000]));
    $dreFloat = new DreAdaptada(fp(['Q09' => 100000.0]));
    expect($dreInt->vendasAnuais())->toBe('1200000.0000');
    expect($dreFloat->vendasAnuais())->toBe('1200000.0000');
});

it('aceita zero como valor válido (não confunde com null)', function () {
    $dre = new DreAdaptada(fp(['Q09' => '0']));
    expect($dre->vendasAnuais())->toBe('0.0000');
});

it('detecta payload não-numérico e retorna null', function () {
    $dre = new DreAdaptada(fp(['Q09' => 'abc']));
    expect($dre->vendasAnuais())->toBeNull();
});
