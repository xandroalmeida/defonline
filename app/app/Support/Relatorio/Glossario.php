<?php

declare(strict_types=1);

namespace App\Support\Relatorio;

/**
 * Glossário do relatório de diagnóstico — textos editoriais derivados do
 * Anexo I (espec V2). Fonte única para a tela (`diagnosticos.show`) e o PDF
 * (`diagnosticos.pdf`). Mudanças no Anexo I propagam por edição manual aqui —
 * o Anexo I não tem motor_version.
 */
final class Glossario
{
    /*
     * Ordem revisada na devolutiva EBC de 27/05/2026 (STORY-042): o EBITDA sobe
     * para 2º item — é o conceito que o Roberto mais precisa antes de ler as
     * margens seguintes. Nenhum item foi removido: o `.docx` pedia "excluir o
     * item antes do Farol/Semáforo" sem nomear qual, e a pergunta ao Edvan
     * (pauta-retorno §3) não teve resposta até o prazo de 2026-06-04 — default
     * documentado é manter, pendência fechada como diferida.
     *
     * @var list<array{0: string, 1: string}>
     */
    public const ITENS = [
        ['Margem Bruta (MB) / Lucro Bruto (LB)', 'Diferença entre o faturamento e o custo (dos produtos/mercadorias/serviços vendidos), antes das despesas.'],
        ['EBITDA / Margem Operacional', 'Resultado do negócio: faturamento menos custos e despesas operacionais, exceto depreciação (e antes das despesas financeiras e tributos). Mede a eficiência em produzir lucros por meio das vendas.'],
        ['Margem Operacional Líquida (MOL)', 'A parcela operacional de lucro obtida sobre as vendas, considerando despesas financeiras, depreciação e tributos.'],
        ['Relação Dívida Líquida / EBITDA', 'Demonstra o número de períodos que uma empresa levaria para pagar sua dívida líquida.'],
        ['NCG / Vendas', 'Representa a relação entre a necessidade de capital de giro e as vendas realizadas. Expressa, em proporção, quanto da receita precisa ser financiada para sustentar o ciclo operacional.'],
        ['PMR — Prazo Médio de Recebimento', 'Tempo médio, em dias, que a empresa leva para receber dos clientes após a venda.'],
        ['PMC — Prazo Médio de pagamento a fornecedores', 'Tempo médio, em dias, que a empresa leva para pagar seus fornecedores após a compra.'],
        ['Ciclo Financeiro (CF)', 'Indica a necessidade de financiamento complementar do capital de giro, em número de dias, em média (se positivo). Fórmula: PME + PMR − PMC.'],
        ['Necessidade de Capital de Giro (NCG)', 'Instrumento de aferição da saúde financeira da empresa, apurada pela diferença entre Ativo e Passivo circulante cíclico. Se positiva, indica quanto de financiamento complementar a empresa necessita.'],
        ['Farol / Semáforo', 'Classificação visual (verde / amarelo / vermelho) atribuída automaticamente a cada indicador a partir das faixas da matriz de recomendações.'],
        ['Indisponível', 'Rótulo exibido quando o indicador não pode ser calculado (ex.: denominador igual a zero ou dado faltante). Não recebe farol nem texto de recomendação.'],
        ['Balanço Adaptado / DRE Adaptada', 'Versão simplificada construída a partir dos 23 campos do quiz; suficiente para os indicadores da v1, mas não equivalente a um balanço/DRE contábil formal.'],
    ];
}
