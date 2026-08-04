<?php

declare(strict_types=1);

use App\Support\Relatorio\ValoresNominais;

/**
 * STORY-044 — valores nominais junto a cada indicador.
 *
 * Este é o teste da regra: quais números em reais compõem cada indicador, e o
 * que acontece quando algum deles não existe no `quiz_payload` persistido.
 *
 * Payload de referência (mesmos números em todos os casos felizes):
 *   Q02  50.000   disponibilidades      Q08  50.000   compras/mês
 *   Q03  80.000   contas a receber      Q09 100.000   vendas/mês
 *   Q04  60.000   estoque               Q14  20.000   despesas fixas/mês
 *   Q05 300.000   imobilizado           Q15   8.000   despesas variáveis/mês
 *   Q06  40.000   dívidas financeiras   Q16   2.000   despesas financeiras/mês
 *   Q07  30.000   fornecedores
 *
 * Derivados (Anexos B/C — mensais × 12):
 *   Vendas 1.200.000 · Compras 600.000 · LB 600.000
 *   Desp. fixas 240.000 · Desp. variáveis 96.000 · EBITDA 264.000
 *   Desp. financeiras 24.000 · LOL 240.000
 *   AT 490.000 · PC 70.000 · PL 420.000
 *   Dívida líquida 40.000 − 50.000 = −10.000 (caixa líquido positivo)
 *   NCG 80.000 + 60.000 − 30.000 = 110.000
 */
function payloadNominalCompleto(): array
{
    return [
        'Q01' => 1,
        'Q02' => '50000.00',
        'Q03' => '80000.00',
        'Q04' => '60000.00',
        'Q05' => '300000.00',
        'Q06' => '40000.00',
        'Q07' => '30000.00',
        'Q08' => '50000.00',
        'Q09' => '100000.00',
        'Q10' => 45,
        'Q11' => 30,
        'Q12' => 45,
        'Q13' => '2.00',
        'Q14' => '20000.00',
        'Q15' => '8000.00',
        'Q16' => '2000.00',
    ];
}

// ---------------------------------------------------------------------------
// CA-1 — cobertura dos indicadores: os dois (ou três) termos, na ordem numerador
// → denominador, para que a leitura "X sobre Y" case com a fórmula do Anexo D.
// ---------------------------------------------------------------------------

it('devolve os termos nominais de cada indicador composto (CA-1)', function (string $codigo, array $esperado) {
    expect(ValoresNominais::doSnapshot(payloadNominalCompleto())->paraIndicador($codigo))
        ->toBe($esperado);
})->with([
    'margem bruta = LB / Vendas' => ['margem_bruta', [
        ['rotulo' => 'Lucro bruto (12 meses)', 'valor' => 'R$ 600.000,00'],
        ['rotulo' => 'Vendas (12 meses)', 'valor' => 'R$ 1.200.000,00'],
    ]],
    'margem EBITDA = EBITDA / Vendas' => ['margem_ebitda', [
        ['rotulo' => 'EBITDA (12 meses)', 'valor' => 'R$ 264.000,00'],
        ['rotulo' => 'Vendas (12 meses)', 'valor' => 'R$ 1.200.000,00'],
    ]],
    'margem líquida = LOL / Vendas' => ['margem_liquida', [
        ['rotulo' => 'Resultado líquido (12 meses)', 'valor' => 'R$ 240.000,00'],
        ['rotulo' => 'Vendas (12 meses)', 'valor' => 'R$ 1.200.000,00'],
    ]],
    'dívida líquida / EBITDA' => ['divida_liquida_ebitda', [
        ['rotulo' => 'Dívida líquida', 'valor' => '-R$ 10.000,00'],
        ['rotulo' => 'EBITDA (12 meses)', 'valor' => 'R$ 264.000,00'],
    ]],
    'despesas financeiras / EBITDA' => ['despesas_fin_ebitda', [
        ['rotulo' => 'Despesas financeiras (12 meses)', 'valor' => 'R$ 24.000,00'],
        ['rotulo' => 'EBITDA (12 meses)', 'valor' => 'R$ 264.000,00'],
    ]],
    'fontes de recursos = PC / PL' => ['fontes_recursos', [
        ['rotulo' => 'Passivo circulante', 'valor' => 'R$ 70.000,00'],
        ['rotulo' => 'Patrimônio líquido', 'valor' => 'R$ 420.000,00'],
    ]],
    'giro do ativo = Vendas / AT' => ['giro_ativo', [
        ['rotulo' => 'Vendas (12 meses)', 'valor' => 'R$ 1.200.000,00'],
        ['rotulo' => 'Ativo total', 'valor' => 'R$ 490.000,00'],
    ]],
    'NCG / Vendas' => ['ncg_vendas', [
        ['rotulo' => 'NCG', 'valor' => 'R$ 110.000,00'],
        ['rotulo' => 'Vendas (12 meses)', 'valor' => 'R$ 1.200.000,00'],
    ]],
    'NCG absoluto = Clientes + Estoque − Fornecedores' => ['ncg_absoluto', [
        ['rotulo' => 'Contas a receber', 'valor' => 'R$ 80.000,00'],
        ['rotulo' => 'Estoque', 'valor' => 'R$ 60.000,00'],
        ['rotulo' => 'Fornecedores a pagar', 'valor' => 'R$ 30.000,00'],
    ]],
]);

// ---------------------------------------------------------------------------
// CA-3 — indicadores sem valor nominal aplicável não inventam número nenhum.
// Os cinco prazos (Q10/Q11/Q12 declarados em dias, e as somas deles) e a
// inadimplência (Q13 declarada em % sobre "vendas a prazo", grandeza que o
// quiz não captura em reais) NÃO têm origem monetária no payload.
// ---------------------------------------------------------------------------

it('não inventa valor nominal para indicadores sem origem monetária (CA-3)', function (string $codigo) {
    expect(ValoresNominais::doSnapshot(payloadNominalCompleto())->paraIndicador($codigo))
        ->toBe([]);
})->with(['pmc', 'pme', 'pmr', 'ciclo_financeiro', 'ciclo_operacional', 'inadimplencia']);

it('devolve lista vazia para código de indicador desconhecido', function () {
    expect(ValoresNominais::doSnapshot(payloadNominalCompleto())->paraIndicador('indicador_futuro_xyz'))
        ->toBe([]);
});

// ---------------------------------------------------------------------------
// CA-8 — compatibilidade retroativa: snapshot antigo/incompleto não quebra e
// não exibe termo pela metade. A resiliência tem que ser POR INDICADOR — um
// campo faltante derruba só quem depende dele.
// ---------------------------------------------------------------------------

it('devolve lista vazia em todos os indicadores quando o payload é vazio (CA-8)', function () {
    $nominais = ValoresNominais::doSnapshot([]);

    foreach (ValoresNominais::CODIGOS_COM_NOMINAL as $codigo) {
        expect($nominais->paraIndicador($codigo))->toBe([], "Esperado vazio para {$codigo}");
    }
});

it('omite apenas os indicadores afetados pelo campo faltante (CA-8)', function (string $campoRemovido, array $codigosOmitidos) {
    $payload = payloadNominalCompleto();
    unset($payload[$campoRemovido]);
    $nominais = ValoresNominais::doSnapshot($payload);

    foreach ($codigosOmitidos as $codigo) {
        expect($nominais->paraIndicador($codigo))->toBe([], "Esperado vazio para {$codigo} sem {$campoRemovido}");
    }

    $intactos = array_diff(ValoresNominais::CODIGOS_COM_NOMINAL, $codigosOmitidos);
    expect($intactos)->not->toBeEmpty();
    foreach ($intactos as $codigo) {
        expect($nominais->paraIndicador($codigo))->not->toBe([], "Esperado nominal preservado em {$codigo} sem {$campoRemovido}");
    }
})->with([
    // Compras: derruba LB e tudo que desce dele (EBITDA, LOL).
    'Q08 (compras)' => ['Q08', ['margem_bruta', 'margem_ebitda', 'margem_liquida', 'divida_liquida_ebitda', 'despesas_fin_ebitda']],
    // Vendas: derruba as três margens (e, via EBITDA, as duas razões que o usam),
    // o giro e o NCG/Vendas — mas não o balanço puro.
    'Q09 (vendas)' => ['Q09', ['margem_bruta', 'margem_ebitda', 'margem_liquida', 'divida_liquida_ebitda', 'despesas_fin_ebitda', 'giro_ativo', 'ncg_vendas']],
    // Despesas financeiras: só a margem líquida e a própria razão desp.fin/EBITDA.
    'Q16 (desp. financeiras)' => ['Q16', ['margem_liquida', 'despesas_fin_ebitda']],
    // Fornecedores: NCG (absoluto e sobre vendas) e o passivo circulante.
    'Q07 (fornecedores)' => ['Q07', ['fontes_recursos', 'ncg_vendas', 'ncg_absoluto']],
    // Imobilizado: só o que passa pelo Ativo Total.
    'Q05 (imobilizado)' => ['Q05', ['fontes_recursos', 'giro_ativo']],
]);

it('trata string vazia e valor nulo como campo ausente (CA-8)', function (mixed $valorDegenerado) {
    $payload = payloadNominalCompleto();
    $payload['Q09'] = $valorDegenerado;

    expect(ValoresNominais::doSnapshot($payload)->paraIndicador('margem_bruta'))->toBe([]);
})->with([
    'null' => [null],
    'string vazia' => [''],
    'não numérico' => ['n/a'],
]);

// ---------------------------------------------------------------------------
// CA-6 — formatação BR. Bordas de sinal, zero e tipo do escalar no payload.
// ---------------------------------------------------------------------------

it('formata lucro bruto negativo com o sinal antes do símbolo (CA-6)', function () {
    $payload = payloadNominalCompleto();
    $payload['Q08'] = '120000.00';  // compras > vendas → LB = −240.000

    expect(ValoresNominais::doSnapshot($payload)->paraIndicador('margem_bruta'))->toBe([
        ['rotulo' => 'Lucro bruto (12 meses)', 'valor' => '-R$ 240.000,00'],
        ['rotulo' => 'Vendas (12 meses)', 'valor' => 'R$ 1.200.000,00'],
    ]);
});

it('formata zero como R$ 0,00 — zero é valor, não ausência (CA-6)', function () {
    $payload = payloadNominalCompleto();
    $payload['Q09'] = '0';

    expect(ValoresNominais::doSnapshot($payload)->paraIndicador('margem_bruta'))->toBe([
        ['rotulo' => 'Lucro bruto (12 meses)', 'valor' => '-R$ 600.000,00'],
        ['rotulo' => 'Vendas (12 meses)', 'valor' => 'R$ 0,00'],
    ]);
});

it('aceita numérico int/float além de string (o canonicalizador não força tipo)', function () {
    $payload = payloadNominalCompleto();
    $payload['Q09'] = 100000;      // int
    $payload['Q08'] = 50000.50;    // float

    expect(ValoresNominais::doSnapshot($payload)->paraIndicador('margem_bruta'))->toBe([
        ['rotulo' => 'Lucro bruto (12 meses)', 'valor' => 'R$ 599.994,00'],
        ['rotulo' => 'Vendas (12 meses)', 'valor' => 'R$ 1.200.000,00'],
    ]);
});

it('formata centavos com duas casas e separador de milhar BR (CA-6)', function () {
    $payload = payloadNominalCompleto();
    $payload['Q03'] = '1234.56';

    expect(ValoresNominais::doSnapshot($payload)->paraIndicador('ncg_absoluto')[0])
        ->toBe(['rotulo' => 'Contas a receber', 'valor' => 'R$ 1.234,56']);
});

// ---------------------------------------------------------------------------
// Contrato do catálogo — protege contra indicador novo entrar sem decisão
// consciente sobre o nominal dele.
// ---------------------------------------------------------------------------

it('o catálogo de indicadores com nominal cobre exatamente os 9 compostos', function () {
    expect(ValoresNominais::CODIGOS_COM_NOMINAL)->toBe([
        'margem_bruta',
        'margem_ebitda',
        'margem_liquida',
        'divida_liquida_ebitda',
        'despesas_fin_ebitda',
        'fontes_recursos',
        'giro_ativo',
        'ncg_vendas',
        'ncg_absoluto',
    ]);
});
