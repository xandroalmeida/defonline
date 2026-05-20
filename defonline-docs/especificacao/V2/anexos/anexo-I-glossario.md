# Anexo I — Glossário Financeiro

**Documento pai:** [`../especificacao-funcional.md`](../especificacao-funcional.md), seção 4.7 (Relatório de Diagnóstico)
**Versão:** 2.1 (alinhado ao modelo de domínio refatorado)
**Data:** 17/05/2026
**Fonte original:** planilha `DEFweb.net - ESTRUTURA - ebc.2025jul30.xlsx`, aba `DEF`, linhas 177–191
**Uso na plataforma:** exibido ao final de todo relatório e disponível em consulta autônoma no painel do assinante. Reutilizável também pelo hotsite, FAQ público e comunicações por e-mail.
**Mantido por:** EB Parcerias Ltda (responsável técnico-financeiro)

---

## I.1 Finalidade

Definições curtas, em linguagem acessível, dos termos técnicos usados no relatório de diagnóstico. Objetivo: permitir ao assinante entender cada indicador e cada linha do balanço/DRE adaptados sem necessidade de conhecimento prévio em contabilidade.

## I.2 Termos

- **Capital de Giro (CDG).** Indica a folga financeira da empresa, apurada pela diferença entre o Passivo Não Circulante e o Ativo Não Circulante.

- **Ciclo Financeiro (CF).** Indica a necessidade de financiamento complementar do capital de giro, em número de dias, em média (se positivo). Fórmula: PME + PMR − PMC.

- **EBITDA** (ou **Margem Operacional**). O resultado do negócio: faturamento menos custos e despesas operacionais, exceto depreciação (e antes das despesas financeiras e tributos). Mede a eficiência da empresa em produzir lucros por meio das vendas.

- **Giro do Ativo (GA).** Indica quantas vezes girou o ativo da empresa durante o período. Fórmula: Vendas ÷ Ativo Total.

- **Investimento Operacional em Giro (IOG).** Ocorre quando a diferença entre ativo e passivo circulante cíclico (operacional) é negativa — significa que sobram recursos.

- **Just in time.** Sistema que determina que produção, transporte, compra e venda devem ocorrer no tempo mais próximo possível da necessidade real, minimizando estoques.

- **Margem Bruta (MB)** / **Lucro Bruto (LB).** Diferença entre o faturamento e o custo (dos produtos/mercadorias/serviços vendidos), antes das despesas.

- **Margem Operacional Líquida (MOL).** A parcela operacional de lucro obtida sobre as vendas, considerando despesas financeiras, depreciação e tributos.

- **Necessidade de Capital de Giro (NCG).** Instrumento de aferição da saúde financeira da empresa, apurada pela diferença entre Ativo e Passivo circulante cíclico. Se positiva, indica quanto de financiamento complementar a empresa necessita.

- **Relação Despesas Financeiras / EBITDA.** Representa a participação dos bancos no lucro.

- **Relação Dívida Líquida / EBITDA.** Demonstra o número de períodos que uma empresa levaria para pagar sua dívida líquida.

- **Relação entre Fontes de Recursos (RFR).** Indica quanto a empresa utiliza de recursos de terceiros para cada unidade de capital próprio; retrata a dependência de recursos externos.

- **NCG / Vendas.** Representa a relação entre a necessidade de capital de giro e as vendas realizadas. Expressa, em proporção, quanto da receita precisa ser financiada para sustentar o ciclo operacional.

## I.3 Termos complementares (não presentes na fonte, adicionados pela Especificação v2)

- **Usuário.** Pessoa física que acessa a plataforma (CPF + e-mail). Detém a assinatura, paga, executa análises. Definição formal em §1.5.2 da especificação funcional.

- **Empresa Analisada.** Entidade (PJ ou autônomo) cujos dados financeiros são analisados — objeto do quiz e do diagnóstico. Um Usuário pode ter várias Empresas Analisadas vinculadas. Definição formal em §1.5.2 da especificação funcional.

- **Cliente** (no relatório). Comprador/consumidor da Empresa Analisada. Aparece em campos como "contas a receber de clientes", "prazo a clientes" (PMR) e "inadimplência de clientes". **Não** é uma entidade da plataforma — é um conceito puramente contábil.

- **Balanço Adaptado.** Versão simplificada do balanço patrimonial usada pelo motor de cálculo da DEFOnline, construída a partir dos 23 campos do quiz; suficiente para os **14 indicadores** da v1 mas **não** equivalente a um balanço contábil formal.

- **DRE Adaptada.** Versão simplificada da Demonstração do Resultado do Exercício, com os valores mensais declarados no quiz (Q08, Q09, Q14, Q15, Q16) anualizados por multiplicação × 12.

- **Farol / Semáforo.** Classificação visual (verde / amarelo / vermelho) atribuída automaticamente a cada indicador a partir das faixas da matriz de recomendações dez/2025.

- **Indisponível.** Rótulo textual exibido no lugar do valor numérico quando o indicador não pode ser calculado (ex.: denominador igual a zero). Não recebe farol nem texto de recomendação.

---

## I.99 Controle de versão

| Versão | Data | Autor | Resumo |
|---|---|---|---|
| 1.0 | 20/04/2026 | EB Parcerias | Primeira versão extraída da planilha ESTRUTURA jul/2025, com termos complementares incorporados pela equipe de produto. |

Alterações neste glossário devem: (1) preservar compatibilidade com relatórios já emitidos; (2) incrementar a versão; (3) comunicar eventuais mudanças semânticas ao time de UX para ajuste no hotsite e FAQ.
