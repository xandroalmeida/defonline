<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * STORY-067 / PDR-013 — permissões de admin na conta e glossário no banco, com os
 * termos que estavam no código. A partir daqui, o texto é mantido no backoffice.
 */
return new class extends Migration
{
    /** @var list<array{0: string, 1: string}> */
    private const TERMOS = [
        ['Margem Bruta (MB) / Lucro Bruto (LB)', 'Diferença entre o faturamento e o custo (dos produtos/mercadorias/serviços vendidos), antes das despesas.'],
        ['EBITDA / Margem Operacional', 'Resultado do negócio: faturamento menos custos e despesas operacionais, exceto depreciação (e antes das despesas financeiras e tributos). Mede a eficiência em produzir lucros por meio das vendas.'],
        ['Margem Operacional Líquida (MOL)', 'A parcela operacional de lucro obtida sobre as vendas, considerando despesas financeiras, depreciação e tributos.'],
        ['Relação Dívida Líquida / EBITDA', 'Demonstra o número de períodos que uma empresa levaria para pagar sua dívida líquida.'],
        ['Relação Despesas Financeiras / EBITDA', 'Representa a participação dos bancos no lucro.'],
        ['Relação entre Fontes de Recursos (RFR)', 'Indica quanto a empresa utiliza de recursos de terceiros para cada unidade de capital próprio; retrata a dependência de recursos externos.'],
        ['Giro do Ativo (GA)', 'Indica quantas vezes girou o ativo da empresa durante o período. Fórmula: Vendas ÷ Ativo Total.'],
        ['NCG / Vendas', 'Representa a relação entre a necessidade de capital de giro e as vendas realizadas. Expressa, em proporção, quanto da receita precisa ser financiada para sustentar o ciclo operacional.'],
        ['PMR — Prazo Médio de Recebimento', 'Tempo médio, em dias, que a empresa leva para receber dos clientes após a venda.'],
        ['PMC — Prazo Médio de pagamento a fornecedores', 'Tempo médio, em dias, que a empresa leva para pagar seus fornecedores após a compra.'],
        ['PME — Prazo Médio de Permanência dos Estoques', 'Prazo médio de permanência em estoque, em dias.'],
        ['Inadimplência', 'Percentual médio de atraso de clientes de vendas a prazo.'],
        ['Ciclo Financeiro (CF)', 'Indica a necessidade de financiamento complementar do capital de giro, em número de dias, em média (se positivo). Fórmula: PME + PMR − PMC.'],
        ['Necessidade de Capital de Giro (NCG)', 'Instrumento de aferição da saúde financeira da empresa, apurada pela diferença entre Ativo e Passivo circulante cíclico. Se positiva, indica quanto de financiamento complementar a empresa necessita.'],
        ['Farol / Semáforo', 'Classificação visual (verde / amarelo / vermelho) atribuída automaticamente a cada indicador a partir das faixas da matriz de recomendações.'],
        ['Indisponível', 'Rótulo exibido quando o indicador não pode ser calculado (ex.: denominador igual a zero ou dado faltante). Não recebe farol nem texto de recomendação.'],
        ['Balanço Adaptado / DRE Adaptada', 'Versão simplificada construída a partir dos 23 campos do quiz; suficiente para os indicadores da v1, mas não equivalente a um balanço/DRE contábil formal.'],
    ];

    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->jsonb('permissoes_admin')->nullable();
        });

        Schema::create('glossario_termos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('termo', 120)->unique();
            $table->text('definicao');
            $table->integer('ordem');
            $table->timestampsTz();

            $table->index('ordem');
        });

        foreach (self::TERMOS as $i => [$termo, $definicao]) {
            DB::table('glossario_termos')->insert([
                'id' => (string) Str::uuid7(),
                'termo' => $termo,
                'definicao' => $definicao,
                'ordem' => $i + 1,
                'created_at' => DB::raw('now()'),
                'updated_at' => DB::raw('now()'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('glossario_termos');

        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('permissoes_admin');
        });
    }
};
