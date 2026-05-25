<?php

declare(strict_types=1);

/**
 * Faixas de farol por indicador — setor Indústria (espec V2.5 §4.5 + Anexo E).
 *
 * Formato por indicador:
 *
 *   'tipo' => 'maior_melhor' | 'menor_melhor'
 *   'verde'    => ['op' => '>',  'valor' => float]   // limite inferior exclusivo (maior_melhor)
 *                | ['op' => '<=', 'valor' => float]  // limite superior inclusivo (menor_melhor)
 *   'amarelo'  => ['min' => float, 'max' => float]   // intervalo (limítrofe)
 *   'vermelho' => ['op' => '<=', 'valor' => float]   // (maior_melhor)
 *                | ['op' => '>',  'valor' => float]  // (menor_melhor)
 *
 * As faixas seguem o Anexo E literalmente; a fronteira de cada faixa está
 * documentada em coluna "Amarelo" (intervalo fechado) entre verde e vermelho.
 *
 * Indicadores **informativos sem farol** (ex.: NCG absoluto) NÃO aparecem aqui
 * — sua classificação semântica é codificada na classe do próprio indicador.
 *
 * Indicadores cobertos por esta config (STORY-028 — V1):
 *   - margem_bruta, margem_liquida, divida_liquida_ebitda, ncg_vendas,
 *     pmr, pmc, ciclo_financeiro.
 *
 * Indicadores adicionados na STORY-030 (V2 = motor 1.1.0):
 *   - margem_ebitda, despesas_fin_ebitda, fontes_recursos, giro_ativo,
 *     pme, inadimplencia.
 */
return [
    // 1) Margem Bruta — § Anexo E (Indústria): > 25% verde · 20.01–25% amarelo · ≤ 20% vermelho.
    'margem_bruta' => [
        'tipo' => 'maior_melhor',
        'verde' => ['op' => '>', 'valor' => 25.0],
        'amarelo' => ['min' => 20.0, 'max' => 25.0],   // (20, 25]
        'vermelho' => ['op' => '<=', 'valor' => 20.0],
    ],

    // 3) Margem Líquida — Indústria: > 15% verde · 8.01–15% amarelo · ≤ 8% vermelho.
    'margem_liquida' => [
        'tipo' => 'maior_melhor',
        'verde' => ['op' => '>', 'valor' => 15.0],
        'amarelo' => ['min' => 8.0, 'max' => 15.0],   // (8, 15]
        'vermelho' => ['op' => '<=', 'valor' => 8.0],
    ],

    // 4) Dívida Líquida / EBITDA — Todos: ≤ 2 verde · 2.01–3 amarelo · > 3 vermelho.
    'divida_liquida_ebitda' => [
        'tipo' => 'menor_melhor',
        'verde' => ['op' => '<=', 'valor' => 2.0],
        'amarelo' => ['min' => 2.0, 'max' => 3.0],   // (2, 3]
        'vermelho' => ['op' => '>', 'valor' => 3.0],
    ],

    // 10) NCG / Vendas — Todos: ≤ 0 verde · 0.01–10% amarelo · > 10% vermelho.
    //     Faixa expressa em fração (0.10 = 10%). O indicador calcula em fração.
    'ncg_vendas' => [
        'tipo' => 'menor_melhor',
        'verde' => ['op' => '<=', 'valor' => 0.0],
        'amarelo' => ['min' => 0.0, 'max' => 0.10],  // (0, 0.10]
        'vermelho' => ['op' => '>', 'valor' => 0.10],
    ],

    // 11) PMC — Todos: > 60 dias verde · 30.01–60 amarelo · ≤ 30 vermelho.
    //     (PMC alto = pago tarde a fornecedores = vantagem operacional.)
    'pmc' => [
        'tipo' => 'maior_melhor',
        'verde' => ['op' => '>', 'valor' => 60.0],
        'amarelo' => ['min' => 30.0, 'max' => 60.0],
        'vermelho' => ['op' => '<=', 'valor' => 30.0],
    ],

    // 13) PMR — Todos: ≤ 30 dias verde · 30.01–60 amarelo · > 60 vermelho.
    'pmr' => [
        'tipo' => 'menor_melhor',
        'verde' => ['op' => '<=', 'valor' => 30.0],
        'amarelo' => ['min' => 30.0, 'max' => 60.0],
        'vermelho' => ['op' => '>', 'valor' => 60.0],
    ],

    // 8) Ciclo Financeiro — Todos: ≤ 30 dias verde · 30.01–60 amarelo · > 60 vermelho.
    'ciclo_financeiro' => [
        'tipo' => 'menor_melhor',
        'verde' => ['op' => '<=', 'valor' => 30.0],
        'amarelo' => ['min' => 30.0, 'max' => 60.0],
        'vermelho' => ['op' => '>', 'valor' => 60.0],
    ],
];
