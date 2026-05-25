<?php

declare(strict_types=1);

use App\Domain\Motor\Farol;
use App\Domain\Motor\MatrizRecomendacoes;
use Illuminate\Support\Facades\Log;
use Mockery;

/*
|-------------------------------------------------------------------------
| MatrizRecomendacoes — STORY-032.
|
| Cobre CA-7 (≥1 por indicador × farol), CA-5 (fallback gracioso), e
| determinismo (mesmo input → mesma saída). O teste arquitetural de carga
| da config vive em MatrizArchTest.
|-------------------------------------------------------------------------
*/

it('devolve texto literal do Anexo F para cada par (codigo × farol)', function (string $codigo, string $farol, string $textoEsperado) {
    expect((new MatrizRecomendacoes)->texto($codigo, $farol))->toBe($textoEsperado);
})->with([
    // F.1 Margem Bruta
    ['margem_bruta', Farol::VERDE, 'Buscar manter a margem atual.'],
    ['margem_bruta', Farol::AMARELO, 'Revisar custos e apurar as margens de contribuição.'],
    ['margem_bruta', Farol::VERMELHO, 'Reduzir custos na compra de matéria-prima e insumos, identificar produtos com menor margem de contribuição.'],

    // F.2 Margem EBITDA
    ['margem_ebitda', Farol::VERDE, 'Acompanhar a melhoria contínua da margem atual.'],
    ['margem_ebitda', Farol::AMARELO, 'Identificar necessidade de enxugamento de estruturas, acompanhar custos e despesas, principalmente fixos.'],
    ['margem_ebitda', Farol::VERMELHO, 'Enxugar estruturas, reduzir custos e despesas, estabelecer módica retirada para os sócios.'],

    // F.3 Margem Líquida
    ['margem_liquida', Farol::VERDE, 'Acompanhar os números com vistas a melhorar a margem atual.'],
    ['margem_liquida', Farol::AMARELO, 'Reduzir despesas com juros.'],
    ['margem_liquida', Farol::VERMELHO, 'Não confundir faturamento com lucro, reduzir despesas financeiras.'],

    // F.4 Dívida Líquida / EBITDA
    ['divida_liquida_ebitda', Farol::VERDE, 'Manter acompanhamento.'],
    ['divida_liquida_ebitda', Farol::AMARELO, 'Evitar empréstimos de curto prazo e juros elevados.'],
    ['divida_liquida_ebitda', Farol::VERMELHO, 'Fugir de empréstimos de curto prazo e juros elevados, imobilizar somente o indispensável, postergar investimentos.'],

    // F.5 Despesas Financeiras / EBITDA
    ['despesas_fin_ebitda', Farol::VERDE, 'Acompanhar com rigor as despesas financeiras.'],
    ['despesas_fin_ebitda', Farol::AMARELO, 'Reduzir empréstimos de curto prazo e juros elevados, monitorar o crescimento das despesas financeiras.'],
    ['despesas_fin_ebitda', Farol::VERMELHO, 'Reduzir despesas financeiras, fugir de empréstimos de curto prazo e juros elevados, imobilizar somente o indispensável, postergar investimentos.'],

    // F.6 Fontes de Recursos
    ['fontes_recursos', Farol::VERDE, 'Acompanhar empréstimos, financiamentos e outras dívidas.'],
    ['fontes_recursos', Farol::AMARELO, 'Evitar empréstimos e financiamentos.'],
    ['fontes_recursos', Farol::VERMELHO, 'Identificar eventuais ativos não operacionais que possam ser transformados em Capital de Giro Próprio (CDG), imobilizar somente o indispensável, postergar investimentos.'],

    // F.7 Giro do Ativo
    ['giro_ativo', Farol::VERDE, 'Acompanhar diariamente o nível de estoques.'],
    ['giro_ativo', Farol::AMARELO, 'Gerenciar estoques para manter somente o mínimo necessário (just in time).'],
    ['giro_ativo', Farol::VERMELHO, 'Gerenciar estoques para manter somente o mínimo necessário (just in time), ajustar os pedidos e compras com vendas, prazo de pagamento a fornecedores e recebimento de clientes.'],

    // F.8 Ciclo Financeiro
    ['ciclo_financeiro', Farol::VERDE, 'Acompanhar o prazo para pagamento a fornecedores e recebimento das vendas.'],
    ['ciclo_financeiro', Farol::AMARELO, 'Aumentar o prazo para pagamento a fornecedores, identificar possíveis alternativas de redução do prazo de recebimento das vendas, implementar estratégias de aumento da rotação das mercadorias dos estoques.'],
    ['ciclo_financeiro', Farol::VERMELHO, 'Vender com prazo menor, ampliar a carteira de clientes, a participação no mercado e aumentar o prazo para pagamento a fornecedores, reduzir o descasamento das contas a pagar e a receber.'],

    // F.10 NCG / Vendas (verde=Negativa, amarelo=0,01–10%, vermelho=>10%)
    ['ncg_vendas', Farol::VERDE, 'Pode indicar que a empresa não necessita de recursos para o giro de seus negócios.'],
    ['ncg_vendas', Farol::AMARELO, 'Manter-se alerta às vendas a prazo, acompanhar o crescimento da rentabilidade e disponibilidade de capital de giro para financiar as vendas a prazo.'],
    ['ncg_vendas', Farol::VERMELHO, 'Não é recomendável a elevação das atividades e da NCG, se não acompanhada do aumento do prazo para pagamento a fornecedores.'],

    // F.11 PMC
    ['pmc', Farol::VERDE, 'Conciliar pedidos com compras, estoques e nível de vendas.'],
    ['pmc', Farol::AMARELO, 'Aumentar prazo para pagamento a fornecedores.'],
    ['pmc', Farol::VERMELHO, 'Aumentar prazo para pagamento a fornecedores, ajustar pedidos a compras, estoques e vendas.'],

    // F.12 PME
    ['pme', Farol::VERDE, 'Gerenciar a rotação do estoque, ampliar a carteira de clientes e aumentar a participação no mercado.'],
    ['pme', Farol::AMARELO, 'Acompanhar a gestão do estoque e implantar estratégias para aumento da rotação das mercadorias dos estoques.'],
    ['pme', Farol::VERMELHO, 'Gerenciar o estoque para manter somente o mínimo necessário (just in time), ampliar a carteira de clientes, aumentar a participação no mercado, aumentar a rotação das mercadorias dos estoques.'],

    // F.13 PMR
    ['pmr', Farol::VERDE, 'Pugnar para que clientes paguem no menor possível, sempre.'],
    ['pmr', Farol::AMARELO, 'Procurar vender com o menor prazo possível.'],
    ['pmr', Farol::VERMELHO, 'Ampliar a carteira de clientes, aumentar a participação no mercado, reduzir o prazo de recebimento das vendas, revisar a política de crédito.'],

    // F.14 Inadimplência
    ['inadimplencia', Farol::VERDE, 'Manter-se atento à gestão da carteira de cobrança.'],
    ['inadimplencia', Farol::AMARELO, 'Para inadimplentes, vender somente à vista. Recalibrar a política de vendas a prazo, revisar o processo de análise de crédito e intensificar a cobrança.'],
    ['inadimplencia', Farol::VERMELHO, 'Revisar condições, premissas, parâmetros e procedimentos de análise de crédito para venda a prazo, acompanhar com rigor a carteira de cobrança e, a inadimplentes, vender apenas à vista.'],
]);

it('fallback gracioso — par (codigo × farol) sem texto retorna placeholder e loga warning', function () {
    Log::shouldReceive('warning')->once()->with('matriz.recomendacao.lacuna', Mockery::on(function ($ctx) {
        return $ctx['codigo'] === 'indicador_inexistente'
            && $ctx['farol'] === Farol::VERMELHO
            && $ctx['setor'] === 'industria'
            && $ctx['matrix_version'] === 'dez-2025';
    }));

    $texto = (new MatrizRecomendacoes)->texto('indicador_inexistente', Farol::VERMELHO);

    expect($texto)->toBe(MatrizRecomendacoes::FALLBACK);
});

it('fallback gracioso — farol válido mas codigo conhecido sem essa cor retorna placeholder', function () {
    // ncg_absoluto está no config mas com chaves positiva/negativa, não verde/amarelo/vermelho.
    Log::shouldReceive('warning')->once();

    $texto = (new MatrizRecomendacoes)->texto('ncg_absoluto', Farol::VERDE);

    expect($texto)->toBe(MatrizRecomendacoes::FALLBACK);
});

it('determinismo — 100 chamadas para o mesmo par produzem string bit-exata', function () {
    $primeiro = (new MatrizRecomendacoes)->texto('margem_bruta', Farol::VERDE);
    $saidas = [];
    for ($i = 0; $i < 100; $i++) {
        $saidas[] = (new MatrizRecomendacoes)->texto('margem_bruta', Farol::VERDE);
    }
    expect(array_unique($saidas))->toBe([$primeiro]);
});

it('auto-append idempotente em matriz-lacunas.md — escreve uma linha por par lacunoso', function () {
    Log::spy();
    $tmpFile = sys_get_temp_dir().'/matriz-lacunas-test-'.uniqid().'.md';
    config(['motor.matriz_lacunas_path' => $tmpFile]);

    try {
        (new MatrizRecomendacoes)->texto('codigo_a', Farol::VERDE);
        (new MatrizRecomendacoes)->texto('codigo_a', Farol::VERDE); // mesma lacuna 2x
        (new MatrizRecomendacoes)->texto('codigo_b', Farol::AMARELO);

        $conteudo = (string) file_get_contents($tmpFile);
        expect($conteudo)
            ->toContain('# Lacunas da Matriz de Recomendações')
            ->toContain('- `codigo_a` × `verde`')
            ->toContain('- `codigo_b` × `amarelo`');

        // Idempotência: a linha de codigo_a aparece exatamente 1 vez.
        expect(substr_count($conteudo, '- `codigo_a` × `verde`'))->toBe(1);
    } finally {
        @unlink($tmpFile);
    }
});

it('auto-append silencia quando o diretório do path não existe', function () {
    Log::spy();
    config(['motor.matriz_lacunas_path' => '/tmp/nao-existe-mesmo/qualquer/matriz-lacunas.md']);

    // Não pode lançar exception.
    $texto = (new MatrizRecomendacoes)->texto('codigo_x', Farol::VERMELHO);
    expect($texto)->toBe(MatrizRecomendacoes::FALLBACK);
});

it('auto-append silencia quando matriz_lacunas_path é string vazia (desligado)', function () {
    Log::spy();
    config(['motor.matriz_lacunas_path' => '']);

    $texto = (new MatrizRecomendacoes)->texto('codigo_y', Farol::VERMELHO);
    expect($texto)->toBe(MatrizRecomendacoes::FALLBACK);
});
