<?php

declare(strict_types=1);

/**
 * Tooltips (boxes explicativos) por campo do quiz — setor Indústria.
 *
 * Fontes:
 *   - Espec. funcional V2 §6.8 (formato: conceito + exemplo numérico + dica
 *     de como obter, em 2–4 linhas).
 *   - Anexo A §A.6 (tabela consolidada, versão 1.0, 2026-05-25).
 *   - Planilha `DEFweb.net - QUIZ.xlsx` coluna `DESCRIÇÕES EM ÍCONES (?)`
 *     (autoria EB Parcerias / EBC) — base original dos campos Q02 a Q16.
 *   - Q01 e Q17–Q23: redigidos pela equipe de produto em 2026-05-25
 *     (marcados como rascunho a confirmar com EBC na próxima revisão).
 *
 * Convenções:
 *   - Chave = identificador do campo no Anexo A (`Q01`..`Q23`).
 *   - Valor = string contendo o texto do tooltip (até ~50 palavras).
 *   - Sem markdown — o componente `<x-help>` (STORY-033) renderiza como
 *     texto simples; ênfases em **negrito** são suportadas via Blade.
 *
 * Edição: ao alterar/adicionar texto, atualize também o Anexo A §A.6 e
 * incremente a versão deste arquivo no comentário `version` abaixo.
 */
return [
    'version' => '1.0.0', // 2026-05-25 — primeira versão consolidada (STORY-033 pré-entrega).

    'campos' => [
        'Q01' => 'Define o setor da Empresa Analisada — escolha o que mais se aproxima da sua atividade principal. Cada setor tem faixas de farol e textos de recomendação próprios (matriz dez/2025). Exemplo: marcenaria, metalúrgica, padaria com produção própria = Indústria. Loja de roupas, mercadinho = Comércio. Escritório de contabilidade, oficina mecânica, salão = Serviços. Dica: se a empresa **transforma matéria-prima** em produto vendido, é Indústria. Se **revende** sem transformar, é Comércio. Se **executa** trabalho/atendimento sem produto físico, é Serviços.',

        'Q02' => 'Valor total disponível em caixa mais saldos em bancos e aplicações de liquidez imediata. Exemplo: R$ 3.000 no caixa + R$ 25.000 na conta corrente + R$ 12.000 em CDB resgatável no dia = R$ 40.000. Dica: olhe o extrato bancário de hoje e some o dinheiro físico. Não inclua investimentos com prazo de carência (CDB longo, fundos de prazo).',

        'Q03' => 'Valor total a receber de **clientes** (os compradores da empresa) por vendas a prazo: duplicatas, cheques pós-datados, cartão de crédito, boletos, caderneta, fiado. Exemplo: R$ 18.000 em duplicatas + R$ 4.500 em cheques + R$ 11.000 em cartão a receber = R$ 33.500. Dica: somar tudo que ainda não entrou no caixa mas vai entrar. Não confundir com vendas já recebidas no mês.',

        'Q04' => 'Valor total dos estoques: produtos prontos, mercadorias, matéria-prima e insumos. Exemplo: marcenaria com R$ 8.000 em madeira + R$ 5.000 em ferragens + R$ 12.000 em móveis prontos = R$ 25.000. Dica: avaliação pelo custo de aquisição (quanto você pagou). Inventário recente ajuda; se não tem, faça estimativa rápida por categoria.',

        'Q05' => 'Valor venal de mercado dos imóveis, equipamentos e veículos da empresa — estimativa em hipótese de venda rápida. Exemplo: galpão R$ 220.000 + máquinas R$ 80.000 + caminhão R$ 35.000 = R$ 335.000. Dica: pense "quanto eu conseguiria por isso se precisasse vender rápido?" — não o valor de balanço contábil nem o de seguro.',

        'Q06' => 'Soma de todas as dívidas com instituições financeiras: empréstimos, financiamentos, cheque especial, antecipações. **Não** inclua fornecedores. Exemplo: R$ 45.000 em empréstimo bancário + R$ 18.000 de financiamento de máquina + R$ 3.500 de cheque especial = R$ 66.500. Dica: somar saldo devedor atual de todos os contratos com banco/financeira. Olhar o último extrato de cada um.',

        'Q07' => 'Soma de todas as obrigações comerciais em aberto com fornecedores (matéria-prima, mercadorias, insumos). Exemplo: R$ 12.000 em boletos de fornecedores + R$ 4.500 em duplicatas a pagar = R$ 16.500. Dica: contas a pagar pendentes hoje. Não somar contratos futuros ainda não faturados.',

        'Q08' => 'Valor médio mensal das compras realizadas nos últimos 12 meses: mercadorias, matéria-prima e insumos. Exemplo: se em 12 meses comprou R$ 240.000 em insumos, a média mensal é R$ 20.000. Dica: somar valor das notas fiscais de compra do ano e dividir por 12. Não incluir compras de imobilizado (máquinas, veículos).',

        'Q09' => 'Valor médio mensal das vendas realizadas nos últimos 12 meses — receita operacional bruta. Exemplo: faturou R$ 480.000 no ano = média mensal R$ 40.000. Dica: somar valor das notas fiscais de venda emitidas no ano e dividir por 12. Considerar o bruto (antes de impostos).',

        'Q10' => 'Prazo médio de pagamento das compras a fornecedores, em **dias**. Exemplo: se você compra para pagar em 30/60/90, a média é 60 dias. Dica: se varia muito entre fornecedores, calcule a média ponderada pelo valor das compras. Em dúvida, use o prazo mais comum.',

        'Q11' => 'Prazo médio em **dias** que o estoque demora a girar (entrar e sair). Considere produtos com permanência maior que 60 dias. Exemplo: se compra mensalmente e o estoque dura ~75 dias na prateleira/depósito, o PME é 75 dias. Dica: PME = (estoque médio ÷ custo das vendas) × 360. Empresas de serviços puros costumam ter PME = 0.',

        'Q12' => 'Prazo médio de recebimento das vendas a prazo, em **dias** — quanto tempo entre vender e receber. Exemplo: se você vende no cartão em 4 parcelas e recebe a média em 60 dias, o PMR é 60. Dica: pondere prazos diferentes pelo volume. Venda à vista entra como 0 dias.',

        'Q13' => 'Percentual médio de atraso dos clientes nas vendas a prazo (%). Valores acima de 100% não são aceitos. Exemplo: se de cada R$ 100 vendidos a prazo, R$ 5 atrasam mais de 30 dias, inadimplência = 5%. Dica: olhe o relatório de duplicatas vencidas ou contas a receber do seu sistema. Se não tem controle preciso, estime conservadoramente.',

        'Q14' => 'Valor médio mensal de saídas do caixa para custos fixos: folha de pagamento (com encargos), contador, aluguel, condomínio, energia, água, telefone/internet, franquia, tarifas, retirada de sócios, demais despesas fixas. Exemplo: folha R$ 18.000 + aluguel R$ 5.000 + serviços/tarifas R$ 4.000 + retiradas R$ 8.000 = R$ 35.000. Dica: somar o que sai todo mês independentemente do volume de vendas. Não incluir tributos sobre venda (vão em variáveis).',

        'Q15' => 'Valor médio mensal de saídas para custos variáveis: fretes, comissões de venda, tributos sobre vendas (impostos, taxas, contribuições) e demais despesas variáveis. Exemplo: tributos sobre vendas R$ 6.000 + comissões R$ 2.500 + frete R$ 1.500 = R$ 10.000. Dica: o que aumenta proporcionalmente quando você vende mais. Se Simples Nacional, o DAS entra aqui.',

        'Q16' => 'Valor médio mensal pago em **juros** por empréstimos, financiamentos, antecipação de recebíveis, cheque especial, desconto de duplicatas. Exemplo: parcelas de empréstimo (juros R$ 1.800) + taxa de cartão (R$ 600) + cheque especial (R$ 250) = R$ 2.650. Dica: somar **só os juros**, não as amortizações (parcelas de devolução do principal). Olhar a linha "juros" no extrato bancário.',

        'Q17' => 'Indica se você precisa captar dinheiro novo (empréstimo, financiamento, sócio investidor). Habilita perguntas extras sobre quanto e como. Exemplo: Sim → você quer crédito para capital de giro, comprar máquina, expandir. Não → empresa não está buscando recursos novos no momento. Dica: responder "Sim" ativa o bloco final do quiz (Q18 a Q23) com perguntas sobre o valor desejado e dados de garantia. Se "Não", o diagnóstico foca só na saúde financeira atual.',

        'Q18' => 'Quanto você precisa captar de recursos financeiros (R$). É o montante total que pretende buscar no mercado. Exemplo: comprar máquina nova de R$ 60.000 + capital de giro de R$ 40.000 = R$ 100.000. Dica: pense no valor total da necessidade, não na parcela mensal. O diagnóstico avalia se sua empresa tem capacidade para essa captação.',

        'Q19' => 'Total do endividamento atual da empresa **somado às dívidas pessoais dos sócios** ligadas ao negócio (bancos, financeiras, particulares). Exemplo: dívida da empresa R$ 80.000 + empréstimo pessoal do sócio para a empresa R$ 25.000 = R$ 105.000. Dica: inclua tudo que afetaria a capacidade da empresa de obter novo crédito — saldo devedor de contratos, agiotas, dívidas com fornecedores em atraso.',

        'Q20' => 'Valor médio mensal das vendas que entram via cartão de crédito ou duplicatas (não inclui venda à vista em dinheiro/Pix). Deve ser **menor que Q09** (venda total mensal). Exemplo: vende R$ 40.000/mês no total e 65% é cartão/duplicata = R$ 26.000. Dica: base para calcular sua capacidade de garantia (limite aproximado de 3× esse valor). É o que os bancos olham para crédito com recebíveis.',

        'Q21' => 'CPF do primeiro sócio responsável pela empresa. Usado para análise de capacidade dos sócios na captação. Dica: formato 000.000.000-00. Dígitos verificadores são checados automaticamente.',

        'Q22' => 'CPF do segundo sócio, se houver. Opcional caso a empresa tenha apenas 1 sócio. Dica: deixe em branco se não aplicável. Quando preenchido, formato 000.000.000-00.',

        'Q23' => 'CPF do terceiro sócio, se houver. Opcional. Dica: deixe em branco se não aplicável. Empresas com 4+ sócios serão tratadas em ondas futuras.',
    ],

    /**
     * Campos cujos textos foram redigidos pela equipe de produto (não vêm da
     * planilha QUIZ original da EBC). Mantém visibilidade até confirmação.
     */
    'rascunhos_a_confirmar_ebc' => ['Q01', 'Q17', 'Q18', 'Q19', 'Q20', 'Q21', 'Q22', 'Q23'],
];
