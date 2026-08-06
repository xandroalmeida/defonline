<?php

declare(strict_types=1);

/**
 * Matriz de Recomendações — versão dez/2025, setor Indústria.
 *
 * Fonte: `defonline-docs/especificacao/V2/anexos/anexo-F-matriz-recomendacoes-dez2025.md`,
 * coluna **Indústria** (autoria EB Parcerias / EBC). Os textos são transcritos
 * **literalmente** do Anexo F, exceto pela remoção do prefixo de faixa numérica
 * entre parênteses (ex.: `(até 20%)`) — decisão registrada na STORY-032: a faixa
 * já é visível ao usuário via valor + farol da linha do indicador, então o
 * prefixo é ruído visual (especialmente em mobile).
 *
 * Estrutura:
 *
 *   13 indicadores com farol verde/amarelo/vermelho — **tudo o que o runtime lê**:
 *     [codigo_indicador => ['verde' => texto, 'amarelo' => texto, 'vermelho' => texto]]
 *
 *   1 seção `_referencia_editorial`, no fim do arquivo: transcrição do Anexo F
 *   que **nenhum código consome** (hoje, só o NCG absoluto §F.13). Está lá por
 *   auditoria editorial, não por configuração. Ver o bloco de comentário da
 *   própria seção e o CA-5 da STORY-060.
 *
 * **Imutabilidade.** Esta versão da matriz é `matrix_version = "dez-2025"`. Para
 * editar textos: criar `matriz-<nova-versao>-industria.php`, incrementar
 * `matrix_version` em `config/motor.php`. Diagnósticos persistidos com a versão
 * antiga continuam exibindo o texto da época (snapshot — IDR-010 §sub-decisão 2).
 */
return [
    // F.1 Margem Bruta
    'margem_bruta' => [
        'verde' => 'Buscar manter a margem atual.',
        'amarelo' => 'Revisar custos e apurar as margens de contribuição.',
        'vermelho' => 'Reduzir custos na compra de matéria-prima e insumos, identificar produtos com menor margem de contribuição.',
    ],

    // F.2 Margem EBITDA
    'margem_ebitda' => [
        'verde' => 'Acompanhar a melhoria contínua da margem atual.',
        'amarelo' => 'Identificar necessidade de enxugamento de estruturas, acompanhar custos e despesas, principalmente fixos.',
        'vermelho' => 'Enxugar estruturas, reduzir custos e despesas, estabelecer módica retirada para os sócios.',
    ],

    // F.3 Margem Líquida
    'margem_liquida' => [
        'verde' => 'Acompanhar os números com vistas a melhorar a margem atual.',
        'amarelo' => 'Reduzir despesas com juros.',
        'vermelho' => 'Não confundir faturamento com lucro, reduzir despesas financeiras.',
    ],

    // F.4 Dívida Líquida / EBITDA
    'divida_liquida_ebitda' => [
        'verde' => 'Manter acompanhamento.',
        'amarelo' => 'Evitar empréstimos de curto prazo e juros elevados.',
        'vermelho' => 'Fugir de empréstimos de curto prazo e juros elevados, imobilizar somente o indispensável, postergar investimentos.',
    ],

    // F.5 Despesas Financeiras / EBITDA
    'despesas_fin_ebitda' => [
        'verde' => 'Acompanhar com rigor as despesas financeiras.',
        'amarelo' => 'Reduzir empréstimos de curto prazo e juros elevados, monitorar o crescimento das despesas financeiras.',
        'vermelho' => 'Reduzir despesas financeiras, fugir de empréstimos de curto prazo e juros elevados, imobilizar somente o indispensável, postergar investimentos.',
    ],

    // F.6 Fontes de Recursos (PC/PL)
    'fontes_recursos' => [
        'verde' => 'Acompanhar empréstimos, financiamentos e outras dívidas.',
        'amarelo' => 'Evitar empréstimos e financiamentos.',
        'vermelho' => 'Identificar eventuais ativos não operacionais que possam ser transformados em Capital de Giro Próprio (CDG), imobilizar somente o indispensável, postergar investimentos.',
    ],

    // F.7 Giro do Ativo
    'giro_ativo' => [
        'verde' => 'Acompanhar diariamente o nível de estoques.',
        'amarelo' => 'Gerenciar estoques para manter somente o mínimo necessário (just in time).',
        'vermelho' => 'Gerenciar estoques para manter somente o mínimo necessário (just in time), ajustar os pedidos e compras com vendas, prazo de pagamento a fornecedores e recebimento de clientes.',
    ],

    // F.8 Prazo Médio de Pagamento das Compras (PMC)
    'pmc' => [
        'verde' => 'Conciliar pedidos com compras, estoques e nível de vendas.',
        'amarelo' => 'Aumentar prazo para pagamento a fornecedores.',
        'vermelho' => 'Aumentar prazo para pagamento a fornecedores, ajustar pedidos a compras, estoques e vendas.',
    ],

    // F.9 Prazo Médio de Permanência dos Estoques (PME)
    'pme' => [
        'verde' => 'Gerenciar a rotação do estoque, ampliar a carteira de clientes e aumentar a participação no mercado.',
        'amarelo' => 'Acompanhar a gestão do estoque e implantar estratégias para aumento da rotação das mercadorias dos estoques.',
        'vermelho' => 'Gerenciar o estoque para manter somente o mínimo necessário (just in time), ampliar a carteira de clientes, aumentar a participação no mercado, aumentar a rotação das mercadorias dos estoques.',
    ],

    // F.10 Prazo Médio de Recebimento das Vendas (PMR)
    'pmr' => [
        'verde' => 'Pugnar para que clientes paguem no menor possível, sempre.',
        'amarelo' => 'Procurar vender com o menor prazo possível.',
        'vermelho' => 'Ampliar a carteira de clientes, aumentar a participação no mercado, reduzir o prazo de recebimento das vendas, revisar a política de crédito.',
    ],

    // F.11 Inadimplência de Clientes
    'inadimplencia' => [
        'verde' => 'Manter-se atento à gestão da carteira de cobrança.',
        'amarelo' => 'Para inadimplentes, vender somente à vista. Recalibrar a política de vendas a prazo, revisar o processo de análise de crédito e intensificar a cobrança.',
        'vermelho' => 'Revisar condições, premissas, parâmetros e procedimentos de análise de crédito para venda a prazo, acompanhar com rigor a carteira de cobrança e, a inadimplentes, vender apenas à vista.',
    ],

    // F.12 Ciclo Financeiro
    'ciclo_financeiro' => [
        'verde' => 'Acompanhar o prazo para pagamento a fornecedores e recebimento das vendas.',
        'amarelo' => 'Aumentar o prazo para pagamento a fornecedores, identificar possíveis alternativas de redução do prazo de recebimento das vendas, implementar estratégias de aumento da rotação das mercadorias dos estoques.',
        'vermelho' => 'Vender com prazo menor, ampliar a carteira de clientes, a participação no mercado e aumentar o prazo para pagamento a fornecedores, reduzir o descasamento das contas a pagar e a receber.',
    ],

    // F.14 NCG / Vendas — faixas próprias (Negativa / 0,01–10% / Acima de 10%) mapeadas para verde/amarelo/vermelho.
    // O motor (NcgVendas.php) classifica via faroes-industria.php — verde=Negativa, amarelo=0,01–10%, vermelho=>10%.
    'ncg_vendas' => [
        'verde' => 'Pode indicar que a empresa não necessita de recursos para o giro de seus negócios.',
        'amarelo' => 'Manter-se alerta às vendas a prazo, acompanhar o crescimento da rentabilidade e disponibilidade de capital de giro para financiar as vendas a prazo.',
        'vermelho' => 'Não é recomendável a elevação das atividades e da NCG, se não acompanhada do aumento do prazo para pagamento a fornecedores.',
    ],

    /*
    |---------------------------------------------------------------------------
    | Referência editorial — NÃO consumida em runtime (STORY-060 CA-5).
    |---------------------------------------------------------------------------
    |
    | Tudo acima é servido por `MatrizRecomendacoes::texto($codigo, $farol)`, e a
    | chave de primeiro nível é sempre o código de um indicador com farol. O que
    | está aqui embaixo **não é**: é transcrição do Anexo F guardada para
    | auditoria editorial, sob uma chave que nenhum código procura.
    |
    | Por que existe uma seção só para isso. Até a STORY-060, `ncg_absoluto`
    | morava lá em cima, entre os indicadores consumidos, e parecia configuração
    | ativa — mas `NcgAbsoluto.php` nunca a leu (decisão da STORY-032, reafirmada
    | pelo PDR-006 §2). Quem editasse aquele texto mudaria nada no produto e
    | levaria um tempo até descobrir por quê. A STORY-060 desarmou a armadilha em
    | dois movimentos: a **intenção** deste texto foi para as constantes de
    | `NcgAbsoluto` (que é o que o usuário lê), e a transcrição desceu para cá.
    |
    | Efeito colateral desejado: se alguém um dia chamar
    | `texto('ncg_absoluto', ...)`, cai no fallback e vira lacuna registrada, em
    | vez de receber em silêncio um texto que o produto não exibe.
    |
    | Divergência deliberada em relação ao Anexo F (STORY-042, devolutiva EBC de
    | 27/05/2026): o cenário `positiva` perdeu a primeira frase ("O crescimento
    | das vendas e o aumento do prazo concedido a clientes gera maior NCG.") por
    | revisão do próprio autor do Anexo F (Edvan / EB Parcerias). Este arquivo é
    | a versão vigente; o Anexo F §F.13 ainda carrega o texto longo e precisa ser
    | atualizado pelo PO — é documento editorial de propriedade da EBC.
    */
    '_referencia_editorial' => [
        // F.13 NCG absoluto — informativo, sem farol; 2 cenários no Anexo F.
        'ncg_absoluto' => [
            'positiva' => 'A elevação deve ser acompanhada, na mesma proporção, pelo aumento das fontes de financiamento de longo prazo, preferencialmente de fornecedores.',
            'negativa' => 'Teoricamente, a empresa não necessita de recursos para o giro. NCG negativa significa Investimento Operacional em Giro (IOG).',
        ],
    ],
];
