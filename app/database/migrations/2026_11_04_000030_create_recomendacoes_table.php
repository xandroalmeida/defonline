<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * STORY-068 / PDR-016 — a matriz de recomendações sai do código para o banco, com
 * os 117 textos (13 indicadores × 3 segmentos × 3 faixas). Indústria vem do arquivo
 * que o motor lia até aqui; Comércio e Serviços, do Anexo F (dez/2025). A faixa é só
 * rótulo da tela: quem classifica o farol é `config/motor/faroes-*.php`.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    private const TEXTOS = [
        // industria
        ['industria', 'margem_bruta', 'verde', '25,01% +', 'Buscar manter a margem atual.'],
        ['industria', 'margem_bruta', 'amarelo', '20,01–25%', 'Revisar custos e apurar as margens de contribuição.'],
        ['industria', 'margem_bruta', 'vermelho', 'até 20%', 'Reduzir custos na compra de matéria-prima e insumos, identificar produtos com menor margem de contribuição.'],
        ['industria', 'margem_ebitda', 'verde', '20,01% +', 'Acompanhar a melhoria contínua da margem atual.'],
        ['industria', 'margem_ebitda', 'amarelo', '15,01–20%', 'Identificar necessidade de enxugamento de estruturas, acompanhar custos e despesas, principalmente fixos.'],
        ['industria', 'margem_ebitda', 'vermelho', 'até 15%', 'Enxugar estruturas, reduzir custos e despesas, estabelecer módica retirada para os sócios.'],
        ['industria', 'margem_liquida', 'verde', '15,01% +', 'Acompanhar os números com vistas a melhorar a margem atual.'],
        ['industria', 'margem_liquida', 'amarelo', '8,01–15%', 'Reduzir despesas com juros.'],
        ['industria', 'margem_liquida', 'vermelho', 'até 8%', 'Não confundir faturamento com lucro, reduzir despesas financeiras.'],
        ['industria', 'divida_liquida_ebitda', 'verde', 'até 2', 'Manter acompanhamento.'],
        ['industria', 'divida_liquida_ebitda', 'amarelo', '2,01–3', 'Evitar empréstimos de curto prazo e juros elevados.'],
        ['industria', 'divida_liquida_ebitda', 'vermelho', '3,01 +', 'Fugir de empréstimos de curto prazo e juros elevados, imobilizar somente o indispensável, postergar investimentos.'],
        ['industria', 'despesas_fin_ebitda', 'verde', 'até 35%', 'Acompanhar com rigor as despesas financeiras.'],
        ['industria', 'despesas_fin_ebitda', 'amarelo', '35,01–50%', 'Reduzir empréstimos de curto prazo e juros elevados, monitorar o crescimento das despesas financeiras.'],
        ['industria', 'despesas_fin_ebitda', 'vermelho', '50,01% +', 'Reduzir despesas financeiras, fugir de empréstimos de curto prazo e juros elevados, imobilizar somente o indispensável, postergar investimentos.'],
        ['industria', 'fontes_recursos', 'verde', 'até 0,5', 'Acompanhar empréstimos, financiamentos e outras dívidas.'],
        ['industria', 'fontes_recursos', 'amarelo', '0,501–1', 'Evitar empréstimos e financiamentos.'],
        ['industria', 'fontes_recursos', 'vermelho', '1,01 +', 'Identificar eventuais ativos não operacionais que possam ser transformados em Capital de Giro Próprio (CDG), imobilizar somente o indispensável, postergar investimentos.'],
        ['industria', 'giro_ativo', 'verde', '2,01 +', 'Acompanhar diariamente o nível de estoques.'],
        ['industria', 'giro_ativo', 'amarelo', '1,01–2', 'Gerenciar estoques para manter somente o mínimo necessário (just in time).'],
        ['industria', 'giro_ativo', 'vermelho', 'até 1', 'Gerenciar estoques para manter somente o mínimo necessário (just in time), ajustar os pedidos e compras com vendas, prazo de pagamento a fornecedores e recebimento de clientes.'],
        ['industria', 'ciclo_financeiro', 'verde', 'até 30 dias', 'Acompanhar o prazo para pagamento a fornecedores e recebimento das vendas.'],
        ['industria', 'ciclo_financeiro', 'amarelo', '30,01–60 dias', 'Aumentar o prazo para pagamento a fornecedores, identificar possíveis alternativas de redução do prazo de recebimento das vendas, implementar estratégias de aumento da rotação das mercadorias dos estoques.'],
        ['industria', 'ciclo_financeiro', 'vermelho', '60,01 + dias', 'Vender com prazo menor, ampliar a carteira de clientes, a participação no mercado e aumentar o prazo para pagamento a fornecedores, reduzir o descasamento das contas a pagar e a receber.'],
        ['industria', 'ncg_vendas', 'verde', 'Negativa', 'Pode indicar que a empresa não necessita de recursos para o giro de seus negócios.'],
        ['industria', 'ncg_vendas', 'amarelo', '0,01–10%', 'Manter-se alerta às vendas a prazo, acompanhar o crescimento da rentabilidade e disponibilidade de capital de giro para financiar as vendas a prazo.'],
        ['industria', 'ncg_vendas', 'vermelho', 'Acima de 10%', 'Não é recomendável a elevação das atividades e da NCG, se não acompanhada do aumento do prazo para pagamento a fornecedores.'],
        ['industria', 'pmc', 'verde', '60,01 + dias', 'Conciliar pedidos com compras, estoques e nível de vendas.'],
        ['industria', 'pmc', 'amarelo', '30,01–60 dias', 'Aumentar prazo para pagamento a fornecedores.'],
        ['industria', 'pmc', 'vermelho', 'até 30 dias', 'Aumentar prazo para pagamento a fornecedores, ajustar pedidos a compras, estoques e vendas.'],
        ['industria', 'pme', 'verde', 'até 30 dias', 'Gerenciar a rotação do estoque, ampliar a carteira de clientes e aumentar a participação no mercado.'],
        ['industria', 'pme', 'amarelo', '30,01–60 dias', 'Acompanhar a gestão do estoque e implantar estratégias para aumento da rotação das mercadorias dos estoques.'],
        ['industria', 'pme', 'vermelho', '60,01 + dias', 'Gerenciar o estoque para manter somente o mínimo necessário (just in time), ampliar a carteira de clientes, aumentar a participação no mercado, aumentar a rotação das mercadorias dos estoques.'],
        ['industria', 'pmr', 'verde', 'até 30 dias', 'Pugnar para que clientes paguem no menor possível, sempre.'],
        ['industria', 'pmr', 'amarelo', '30,01–60 dias', 'Procurar vender com o menor prazo possível.'],
        ['industria', 'pmr', 'vermelho', '60,01 + dias', 'Ampliar a carteira de clientes, aumentar a participação no mercado, reduzir o prazo de recebimento das vendas, revisar a política de crédito.'],
        ['industria', 'inadimplencia', 'verde', 'até 3%', 'Manter-se atento à gestão da carteira de cobrança.'],
        ['industria', 'inadimplencia', 'amarelo', '3,01–5%', 'Para inadimplentes, vender somente à vista. Recalibrar a política de vendas a prazo, revisar o processo de análise de crédito e intensificar a cobrança.'],
        ['industria', 'inadimplencia', 'vermelho', '5,01% +', 'Revisar condições, premissas, parâmetros e procedimentos de análise de crédito para venda a prazo, acompanhar com rigor a carteira de cobrança e, a inadimplentes, vender apenas à vista.'],
        // comercio
        ['comercio', 'margem_bruta', 'verde', '25,01% +', 'Acompanhar os números para manter a margem atual com melhoria contínua.'],
        ['comercio', 'margem_bruta', 'amarelo', '18,01–25%', 'Acompanhar os custos e margens dos itens mais representativos.'],
        ['comercio', 'margem_bruta', 'vermelho', 'até 18%', 'Reduzir o estoque dos produtos com menor margem de contribuição e focar naqueles com maior margem.'],
        ['comercio', 'margem_ebitda', 'verde', '15,01% +', 'Acompanhar a margem atual.'],
        ['comercio', 'margem_ebitda', 'amarelo', '10,01–15%', 'Revisar e acompanhar custos e despesas, principalmente fixos.'],
        ['comercio', 'margem_ebitda', 'vermelho', 'até 10%', 'Reduzir custos e despesas, estabelecer pró-labore adequado.'],
        ['comercio', 'margem_liquida', 'verde', '12,01% +', 'Melhorar a margem atual.'],
        ['comercio', 'margem_liquida', 'amarelo', '6,01–12%', 'Reduzir despesas financeiras.'],
        ['comercio', 'margem_liquida', 'vermelho', 'até 6%', 'Não confundir faturamento com lucro, reduzir despesas financeiras, não misturar o "caixa do negócio" com o "bolso do dono".'],
        ['comercio', 'divida_liquida_ebitda', 'verde', 'até 2', 'Acompanhar o endividamento saudável.'],
        ['comercio', 'divida_liquida_ebitda', 'amarelo', '2,01–3', 'Reduzir dívidas de curto prazo e juros elevados.'],
        ['comercio', 'divida_liquida_ebitda', 'vermelho', '3,01 +', 'Reduzir endividamento, não recorrer a agiotas, evitar empréstimos de curto prazo e juros elevados.'],
        ['comercio', 'despesas_fin_ebitda', 'verde', 'até 20%', 'Acompanhar diariamente o controle de despesas financeiras.'],
        ['comercio', 'despesas_fin_ebitda', 'amarelo', '20,01–40%', 'Evitar empréstimos de curto prazo e juros elevados, acompanhar as despesas financeiras.'],
        ['comercio', 'despesas_fin_ebitda', 'vermelho', '40,01% +', 'Reduzir as Despesas Financeiras, não recorrer a agiotas, fugir de empréstimos de curto prazo e juros elevados.'],
        ['comercio', 'fontes_recursos', 'verde', 'até 0,5', 'Acompanhar dívidas.'],
        ['comercio', 'fontes_recursos', 'amarelo', '0,501–1', 'Acompanhar com rigor o nível de endividamento.'],
        ['comercio', 'fontes_recursos', 'vermelho', '1,01 +', 'Reduzir o endividamento, identificar eventuais ativos não operacionais que possam ser transformados em CDG, imobilizar somente o indispensável, postergar investimentos.'],
        ['comercio', 'giro_ativo', 'verde', '3,01 +', 'Monitorar o nível de estoques.'],
        ['comercio', 'giro_ativo', 'amarelo', '2,01–3', 'Manter estoque somente do necessário.'],
        ['comercio', 'giro_ativo', 'vermelho', 'até 2', 'Gerenciar rigorosamente os estoques, ajustar os pedidos e compras com vendas, prazo de pagamento a fornecedores e recebimento de clientes.'],
        ['comercio', 'ciclo_financeiro', 'verde', 'até 30 dias', 'Acompanhar a rotação das mercadorias dos estoques e o prazo para pagamento a fornecedores e recebimento das vendas.'],
        ['comercio', 'ciclo_financeiro', 'amarelo', '30,01–60 dias', 'Pugnar pelo aumento do prazo para pagamento a fornecedores, identificar possíveis alternativas de redução do prazo de recebimento das vendas, implementar estratégias de aumento da rotação das mercadorias dos estoques.'],
        ['comercio', 'ciclo_financeiro', 'vermelho', '60,01 + dias', 'Ampliar a carteira de clientes e aumentar a participação no mercado, aumentar o prazo para pagamento a fornecedores, reduzir o prazo de recebimento das vendas.'],
        ['comercio', 'ncg_vendas', 'verde', 'Negativa', 'É provável que a empresa não necessite de recursos para o giro de seus negócios.'],
        ['comercio', 'ncg_vendas', 'amarelo', '0,01–10%', 'Observar com rigor o aumento das vendas a prazo, avaliar o crescimento da rentabilidade e se a disponibilidade de capital de giro é suficiente para financiar as vendas a prazo.'],
        ['comercio', 'ncg_vendas', 'vermelho', 'Acima de 10%', 'Manter-se alerta à elevação das atividades e da NCG. Verificar se é acompanhada do aumento do prazo para pagamento a fornecedores.'],
        ['comercio', 'pmc', 'verde', '60,01 + dias', 'Acompanhar pedidos, compras, estoques e nível de vendas.'],
        ['comercio', 'pmc', 'amarelo', '30,01–60 dias', 'Pugnar pelo aumento do prazo a fornecedores.'],
        ['comercio', 'pmc', 'vermelho', 'até 30 dias', 'Aumentar, de imediato, prazo para pagamento a fornecedores e ajustar pedidos ao nível de vendas.'],
        ['comercio', 'pme', 'verde', 'até 30 dias', 'Gerenciar o estoque com muita atenção e ampliar a carteira de clientes.'],
        ['comercio', 'pme', 'amarelo', '30,01–60 dias', 'Acompanhar a gestão do estoque e adotar estratégias para aumento da rotação das mercadorias dos estoques.'],
        ['comercio', 'pme', 'vermelho', '60,01 + dias', 'Gerenciar com rigor o estoque, manter somente o mínimo necessário, ampliar a carteira de clientes, aumentar a participação no mercado e a rotação das mercadorias dos estoques.'],
        ['comercio', 'pmr', 'verde', 'até 30 dias', 'Procurar praticar o menor prazo para recebimento de clientes.'],
        ['comercio', 'pmr', 'amarelo', '30,01–60 dias', 'Vender a prazos menores.'],
        ['comercio', 'pmr', 'vermelho', '60,01 + dias', 'Reduzir o prazo de recebimento das vendas e revisar a política de crédito.'],
        ['comercio', 'inadimplencia', 'verde', 'até 3%', 'Acompanhar a gestão da carteira de cobrança.'],
        ['comercio', 'inadimplencia', 'amarelo', '3,01–5%', 'Realizar cobrança rigorosa da carteira em atraso, a inadimplentes vender somente à vista, revisar política de vendas a prazo e intensificar a cobrança.'],
        ['comercio', 'inadimplencia', 'vermelho', '5,01% +', 'Revisar o processo de análise de crédito, acompanhar com rigor a carteira de cobrança e, a inadimplentes, vender apenas à vista.'],
        // servicos
        ['servicos', 'margem_bruta', 'verde', '30,01% +', 'Manter ou melhorar a margem atual.'],
        ['servicos', 'margem_bruta', 'amarelo', '25,01–30%', 'Apurar as margens de contribuição dos itens mais representativos.'],
        ['servicos', 'margem_bruta', 'vermelho', 'até 25%', 'Identificar itens com menor margem de contribuição e focar naqueles com maior margem.'],
        ['servicos', 'margem_ebitda', 'verde', '30,01% +', 'Manter e melhorar a margem atual.'],
        ['servicos', 'margem_ebitda', 'amarelo', '20,01–30%', 'Acompanhar custos e despesas.'],
        ['servicos', 'margem_ebitda', 'vermelho', 'até 20%', 'Reduzir despesas.'],
        ['servicos', 'margem_liquida', 'verde', '20,01% +', 'Acompanhar a performance.'],
        ['servicos', 'margem_liquida', 'amarelo', '10,01–20%', 'Reduzir despesas.'],
        ['servicos', 'margem_liquida', 'vermelho', 'até 10%', 'Não confundir faturamento com lucro, não misturar o "caixa do negócio" com o "bolso do dono", estabelecer módica retirada para os sócios (pró-labore).'],
        ['servicos', 'divida_liquida_ebitda', 'verde', 'até 2', 'Manter acompanhamento do endividamento.'],
        ['servicos', 'divida_liquida_ebitda', 'amarelo', '2,01–3', 'Evitar dívidas.'],
        ['servicos', 'divida_liquida_ebitda', 'vermelho', '3,01 +', 'Reduzir dívidas, não recorrer a agiotas.'],
        ['servicos', 'despesas_fin_ebitda', 'verde', 'até 30%', 'Evitar despesas financeiras.'],
        ['servicos', 'despesas_fin_ebitda', 'amarelo', '30,01–50%', 'Monitorar o crescimento das despesas financeiras.'],
        ['servicos', 'despesas_fin_ebitda', 'vermelho', '50,01% +', 'Reduzir as Despesas Financeiras, não recorrer a agiotas.'],
        ['servicos', 'fontes_recursos', 'verde', 'até 0,5', 'Reduzir empréstimos, financiamentos e outras dívidas.'],
        ['servicos', 'fontes_recursos', 'amarelo', '0,501–1', 'Evitar dívidas.'],
        ['servicos', 'fontes_recursos', 'vermelho', '1,01 +', 'Evitar endividamento, transformar ativos não operacionais em CDG, imobilizar somente o indispensável, postergar investimentos.'],
        ['servicos', 'giro_ativo', 'verde', '3,01 +', 'Manter e melhorar a performance.'],
        ['servicos', 'giro_ativo', 'amarelo', '2,01–3', 'Acompanhar os números com vistas a melhorar a performance.'],
        ['servicos', 'giro_ativo', 'vermelho', 'até 2', 'Aumentar a participação no mercado e a carteira de clientes, ajustar prazo de pagamentos e recebimentos.'],
        ['servicos', 'ciclo_financeiro', 'verde', 'até 30 dias', 'Acompanhar prazo para pagamento e recebimento de contas.'],
        ['servicos', 'ciclo_financeiro', 'amarelo', '30,01–60 dias', 'Pugnar pelo aumento do prazo para pagamento das contas, identificar possíveis alternativas de redução do prazo de recebimento de clientes.'],
        ['servicos', 'ciclo_financeiro', 'vermelho', '60,01 + dias', 'Ampliar a carteira de clientes e aumentar a participação no mercado, reduzir o prazo de recebimento de clientes.'],
        ['servicos', 'ncg_vendas', 'verde', 'Negativa', 'Indica que o negócio não necessita de recursos para o giro.'],
        ['servicos', 'ncg_vendas', 'amarelo', '0,01–10%', 'Conciliar prazos concedidos com o crescimento da rentabilidade e disponibilidade de capital de giro para financiar o prazo dos recebimentos de clientes.'],
        ['servicos', 'ncg_vendas', 'vermelho', 'Acima de 10%', 'Acompanhar a elevação das atividades e da NCG. Verificar se é acompanhada do aumento do prazo para pagamento a fornecedores.'],
        ['servicos', 'pmc', 'verde', '60,01 + dias', 'Adequar contas a pagar e a receber.'],
        ['servicos', 'pmc', 'amarelo', '30,01–60 dias', 'Acompanhar prazos das contas a pagar.'],
        ['servicos', 'pmc', 'vermelho', 'até 30 dias', 'Pugnar pelo aumento de prazo das contas a pagar.'],
        ['servicos', 'pme', 'verde', 'até 30 dias', 'Não se aplica a serviços.'],
        ['servicos', 'pme', 'amarelo', '30,01–60 dias', 'Não se aplica a prestação de serviços.'],
        ['servicos', 'pme', 'vermelho', '60,01 + dias', 'Não se aplica ao segmento de serviços.'],
        ['servicos', 'pmr', 'verde', 'até 30 dias', 'Buscar receber de clientes no menor prazo possível.'],
        ['servicos', 'pmr', 'amarelo', '30,01–60 dias', 'Receber de clientes no menor prazo possível.'],
        ['servicos', 'pmr', 'vermelho', '60,01 + dias', 'Revisar a política de crédito e reduzir o prazo de recebimento de clientes.'],
        ['servicos', 'inadimplencia', 'verde', 'até 3%', 'Realizar boa gestão da carteira de cobrança.'],
        ['servicos', 'inadimplencia', 'amarelo', '3,01–5%', 'Para inadimplentes, faturar apenas à vista e intensificar a cobrança.'],
        ['servicos', 'inadimplencia', 'vermelho', '5,01% +', 'Acompanhar com rigor a carteira de cobrança.'],
    ];

    public function up(): void
    {
        Schema::create('recomendacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('setor', 16);
            $table->string('indicador', 40);
            $table->string('farol', 10);
            $table->string('faixa', 40);
            $table->string('texto', 300);
            $table->timestampTz('editado_em')->nullable();
            $table->timestampsTz();

            $table->unique(['setor', 'indicador', 'farol']);
        });

        foreach (self::TEXTOS as [$setor, $indicador, $farol, $faixa, $texto]) {
            DB::table('recomendacoes')->insert([
                'id' => (string) Str::uuid7(),
                'setor' => $setor,
                'indicador' => $indicador,
                'farol' => $farol,
                'faixa' => $faixa,
                'texto' => $texto,
                'created_at' => DB::raw('now()'),
                'updated_at' => DB::raw('now()'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recomendacoes');
    }
};
