---
artifact: design-note
title: Casos extremos do motor de cálculo — política de retorno por indicador
related_idr: IDR-010
related_story: STORY-026
related_epic: EPIC-002
status: revised
created_at: 2026-05-25
updated_at: 2026-05-25
revisions:
  - at: 2026-05-25
    by: po (alexandro) via arquiteto
    note: "Correção de Qs após auditoria do Programador (STORY-028). Trocas: Q02 (caixa) é o disponível para Dívida Líquida/EBITDA (era Q04 erroneamente); Q04 é estoques no NCG e Giro do Ativo (era Q05 erroneamente); AT enumerado corretamente como Q02+Q03+Q04+Q05. Motor seguiu o Anexo A (autoritativo) — sem prejuízo funcional, apenas correção de redação do design-note."
---

# Casos extremos por indicador — política de retorno

> **Política única (IDR-010 sub-decisão 5).** Quando o motor não consegue calcular um indicador por **input degenerado conhecido** (divisão por zero, dado faltante, valor negativo inesperado), o snapshot grava `{"valor": null, "farol": "nenhum", "motivo": "<código>", "mensagem": "<texto curto>"}`. **Não** lança exception. Exception fica reservada para invariante de programação (setor desconhecido, indicador não existente).

A spec V2.5 §4.5 já define a categoria de saída `"Indisponível"` (sem farol, sem texto da matriz). Este catálogo concretiza, indicador por indicador, **quais inputs disparam essa saída** e **qual mensagem curta vai ao relatório**.

## Convenções

- Variáveis seguem o Anexo D da spec (Vendas anualizadas = Q09 × 12; Compras anualizadas = Q08 × 12; etc.).
- "Negativo inesperado" = valor < 0 em campo que conceitualmente deveria ser ≥ 0 (ex.: estoques, vendas, PMC).
- "Faltante" = `null` em campo obrigatório do Anexo A para o setor Indústria.
- O código de motivo (`motivo`) é estável; o frontend pode mapear para microcopy. O catálogo abaixo dá a **mensagem padrão** em PT-BR.
- Para PME, vale a regra adicional da spec (Q01=3 Serviços → PME não-aplicável); no EPIC-002 só Indústria entra, então PME é sempre coletada.

## Catálogo

### Indicador 1 — Margem Bruta

Fórmula: `Lucro Bruto / Vendas × 100`. Vendas = Q09 × 12.

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 1.1 | Vendas zero (denominador 0) | `Q09 == 0` ou `Vendas anualizadas == 0` | `motivo: "indisponivel:vendas_zero"` — *"Indicador indisponível: vendas anuais são zero."* |
| 1.2 | Q09 faltante | `Q09 == null` | `motivo: "indisponivel:vendas_faltante"` — *"Indicador indisponível: vendas mensais não informadas."* |
| 1.3 | Vendas negativas (inválido) | `Q09 < 0` | Validação no quiz **bloqueia** antes do motor (CA-2 da STORY-027); se chegar ao motor por bypass, motor levanta exception (invariante violada). |

### Indicador 2 — Margem EBITDA

Fórmula: `EBITDA / Vendas × 100`. EBITDA = LB − Despesas fixas − Despesas variáveis = (Vendas − Compras) − Q14×12 − Q15×12.

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 2.1 | Vendas zero | `Vendas anualizadas == 0` | `motivo: "indisponivel:vendas_zero"` — *"Indicador indisponível: vendas anuais são zero."* |
| 2.2 | EBITDA negativo extremo | EBITDA calculável mas `< -Vendas` (margem < −100%) | `motivo: "indisponivel:ebitda_extremo"` — *"Indicador fora de faixa exibível (margem inferior a −100%). Reveja custos e despesas declarados."* (não bloqueia, mas sinaliza input suspeito) |
| 2.3 | Dado de despesa faltante | `Q14 == null` ou `Q15 == null` | `motivo: "indisponivel:despesas_faltante"` — *"Indicador indisponível: despesas fixas ou variáveis não informadas."* |

### Indicador 3 — Margem Líquida

Fórmula: `LOL / Vendas × 100`. LOL = EBITDA − Despesas financeiras = EBITDA − Q16×12.

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 3.1 | Vendas zero | `Vendas anualizadas == 0` | `motivo: "indisponivel:vendas_zero"` — *"Indicador indisponível: vendas anuais são zero."* |
| 3.2 | Despesa financeira faltante | `Q16 == null` | `motivo: "indisponivel:despesas_financeiras_faltante"` — *"Indicador indisponível: despesas financeiras não informadas."* |

### Indicador 4 — Dívida Líquida / EBITDA

Fórmula: `(PCF − ACF) / EBITDA`. PCF = Q06 dívida financeira; ACF = Q02 recursos disponíveis (caixa + bancos + aplicações).

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 4.1 | EBITDA zero (denominador 0) | EBITDA calculado = 0 | `motivo: "indisponivel:ebitda_zero"` — *"Indicador indisponível: EBITDA é zero."* |
| 4.2 | EBITDA negativo (semântica inválida) | EBITDA < 0 | `motivo: "indisponivel:ebitda_negativo"` — *"Indicador indisponível: EBITDA negativo — empresa em prejuízo operacional. Veja Margem EBITDA."* (a interpretação de "dívida líquida sobre prejuízo" não faz sentido financeiro) |
| 4.3 | Dívida líquida negativa | `(PCF − ACF) < 0` | **Valor calculado normalmente** (negativo é semanticamente válido = "caixa líquido"); farol verde (≤ 2). Não é caso extremo, apenas registrado aqui para evitar tratá-lo como bug. |
| 4.4 | Q02 ou Q06 faltante | `Q02 == null` ou `Q06 == null` | `motivo: "indisponivel:divida_componente_faltante"` — *"Indicador indisponível: dívidas ou disponibilidades não informadas."* |

### Indicador 5 — Desp. Financeiras / EBITDA

Fórmula: `Despesas financeiras / EBITDA × 100`.

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 5.1 | EBITDA zero | EBITDA = 0 | `motivo: "indisponivel:ebitda_zero"` — *"Indicador indisponível: EBITDA é zero."* |
| 5.2 | EBITDA negativo | EBITDA < 0 | `motivo: "indisponivel:ebitda_negativo"` — *"Indicador indisponível: EBITDA negativo. Veja Margem EBITDA."* |
| 5.3 | Q16 faltante | `Q16 == null` | `motivo: "indisponivel:despesas_financeiras_faltante"` — *"Indicador indisponível: despesas financeiras não informadas."* |

### Indicador 6 — Fontes de Recursos (PC / PL)

Fórmula: `PC / PL`. PL = Ativo Total − Passivo Circulante (espec §4.5 — simplificação validada pela EBC). Ativo Total = Q02 (recursos disponíveis) + Q03 (clientes) + Q04 (estoques) + Q05 (patrimônio imobilizado).

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 6.1 | PL zero ou negativo (denominador inválido) | `PL <= 0` | `motivo: "indisponivel:pl_nao_positivo"` — *"Indicador indisponível: patrimônio líquido apurado é zero ou negativo (passivo ≥ ativo). Reveja contas a pagar e dívidas declaradas."* |
| 6.2 | Ativo Total faltante | qualquer componente do AT faltante (Q02 ∨ Q03 ∨ Q04 ∨ Q05) | `motivo: "indisponivel:ativo_componente_faltante"` — *"Indicador indisponível: componentes do ativo não informados."* |

### Indicador 7 — Giro do Ativo

Fórmula: `Vendas / Ativo Total`. AT = Q02 + Q03 + Q04 + Q05.

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 7.1 | Ativo Total zero | AT = 0 | `motivo: "indisponivel:ativo_zero"` — *"Indicador indisponível: ativo total declarado é zero."* |
| 7.2 | Componente do AT faltante | qualquer Q02 ∨ Q03 ∨ Q04 ∨ Q05 = null | `motivo: "indisponivel:ativo_componente_faltante"` — *"Indicador indisponível: componentes do ativo não informados."* |

### Indicador 8 — Ciclo Financeiro

Fórmula: `PME + PMR − PMC`.

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 8.1 | Qualquer prazo faltante | `Q10 == null` ∨ `Q11 == null` ∨ `Q12 == null` | `motivo: "indisponivel:prazo_faltante"` — *"Indicador indisponível: prazos médios (PMC/PME/PMR) não totalmente informados."* |
| 8.2 | Ciclo negativo extremo | `Ciclo < -90 dias` (válido matematicamente mas atípico) | **Valor calculado normalmente** (semanticamente significa que a empresa paga depois de receber e vender — caso "ideal extremo"). Não é caso de `null`; pode disparar validação cruzada para confirmação no quiz (§6.6). |

### Indicador 9 — NCG absoluto (informativo, sem farol)

Fórmula: `Estoques + Clientes − Fornecedores` = `Q04 + Q03 − Q07`. Indicador sempre **informativo** (espec §4.5, decisão 6.3 fechada 17/05/2026 — `farol = "nenhum"` sempre).

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 9.1 | Qualquer componente faltante | Q03 ∨ Q04 ∨ Q07 = null | `motivo: "indisponivel:ncg_componente_faltante"` — *"NCG indisponível: estoques, clientes a receber ou fornecedores não informados."* |
| 9.2 | NCG negativo (folga operacional) | NCG calculado < 0 | **Valor calculado normalmente**; aplica mensagem semântica "folga operacional / capital de giro positivo" (espec §4.5). Sem farol. |
| 9.3 | NCG > 10% das Vendas anualizadas | calculado normalmente | **Valor calculado normalmente**; aplica mensagem "positivo alto crescente / pressão sobre o caixa" (espec §4.5). Sem farol. |

### Indicador 10 — NCG / Vendas

Fórmula: `(ACC − PCC) / Vendas`. ACC = Clientes (Q03) + Estoques (Q04); PCC = Fornecedores (Q07).

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 10.1 | Vendas zero | `Vendas anualizadas == 0` | `motivo: "indisponivel:vendas_zero"` — *"Indicador indisponível: vendas anuais são zero."* |
| 10.2 | Componente do NCG faltante | Q03 ∨ Q04 ∨ Q07 = null | `motivo: "indisponivel:ncg_componente_faltante"` — *"Indicador indisponível: estoques, clientes ou fornecedores não informados."* |

### Indicador 11 — PMC

Fórmula: declarado em Q10 (dias ≥ 0).

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 11.1 | Q10 faltante | `Q10 == null` | `motivo: "indisponivel:pmc_faltante"` — *"Indicador indisponível: prazo de pagamento de fornecedores não informado."* |
| 11.2 | PMC > 365 (atípico) | `Q10 > 365` | Validação cruzada **alerta no quiz** ("Tem certeza? Valor atípico" — espec §6.8); se confirmado, motor calcula normalmente (sem farol verde, classifica como amarelo/vermelho conforme matriz). |

### Indicador 12 — PME

Fórmula: declarado em Q11 (dias ≥ 0). Não aplicável para Serviços (Q01=3); no EPIC-002 (Indústria) sempre obrigatório.

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 12.1 | Q11 faltante (Indústria) | `Q11 == null` ∧ `setor == industria` | `motivo: "indisponivel:pme_faltante"` — *"Indicador indisponível: prazo médio de estoque não informado."* |
| 12.2 | PME > 365 (atípico) | `Q11 > 365` | Validação cruzada **alerta no quiz**; se confirmado, motor classifica como vermelho (faixa > 60 — Indústria). |

### Indicador 13 — PMR

Fórmula: declarado em Q12 (dias ≥ 0).

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 13.1 | Q12 faltante | `Q12 == null` | `motivo: "indisponivel:pmr_faltante"` — *"Indicador indisponível: prazo de recebimento de clientes não informado."* |
| 13.2 | PMR > 365 (atípico) | `Q12 > 365` | Validação cruzada **alerta no quiz**; se confirmado, motor classifica como vermelho (> 60). |

### Indicador 14 — Inadimplência

Fórmula: declarada em Q13 (% ≥ 0, ≤ 100).

| # | Caso | Disparador | Saída |
|---|---|---|---|
| 14.1 | Q13 faltante | `Q13 == null` | `motivo: "indisponivel:inadimplencia_faltante"` — *"Indicador indisponível: índice de inadimplência não informado."* |
| 14.2 | Q13 > 100% (inválido) | `Q13 > 100` | Validação no quiz bloqueia (CA-2 da STORY-027); se chegar ao motor, exception (invariante violada). |

## Tabela-resumo dos códigos de motivo

| Código | Mensagem padrão |
|---|---|
| `indisponivel:vendas_zero` | Indicador indisponível: vendas anuais são zero. |
| `indisponivel:vendas_faltante` | Indicador indisponível: vendas mensais não informadas. |
| `indisponivel:despesas_faltante` | Indicador indisponível: despesas fixas ou variáveis não informadas. |
| `indisponivel:despesas_financeiras_faltante` | Indicador indisponível: despesas financeiras não informadas. |
| `indisponivel:ebitda_zero` | Indicador indisponível: EBITDA é zero. |
| `indisponivel:ebitda_negativo` | Indicador indisponível: EBITDA negativo. Veja Margem EBITDA. |
| `indisponivel:ebitda_extremo` | Indicador fora de faixa exibível. Reveja custos e despesas declarados. |
| `indisponivel:pl_nao_positivo` | Indicador indisponível: patrimônio líquido apurado é zero ou negativo. |
| `indisponivel:ativo_zero` | Indicador indisponível: ativo total declarado é zero. |
| `indisponivel:ativo_componente_faltante` | Indicador indisponível: componentes do ativo não informados. |
| `indisponivel:divida_componente_faltante` | Indicador indisponível: dívidas ou disponibilidades não informadas. |
| `indisponivel:prazo_faltante` | Indicador indisponível: prazos médios (PMC/PME/PMR) não totalmente informados. |
| `indisponivel:ncg_componente_faltante` | NCG indisponível: estoques, clientes ou fornecedores não informados. |
| `indisponivel:pmc_faltante` | Indicador indisponível: prazo de pagamento de fornecedores não informado. |
| `indisponivel:pme_faltante` | Indicador indisponível: prazo médio de estoque não informado. |
| `indisponivel:pmr_faltante` | Indicador indisponível: prazo de recebimento de clientes não informado. |
| `indisponivel:inadimplencia_faltante` | Indicador indisponível: índice de inadimplência não informado. |

## Contagem (DoD CA-5: ≥ 28 entradas)

- 1.1, 1.2, 1.3 — Margem Bruta (3)
- 2.1, 2.2, 2.3 — Margem EBITDA (3)
- 3.1, 3.2 — Margem Líquida (2)
- 4.1, 4.2, 4.3, 4.4 — Dívida Líq./EBITDA (4)
- 5.1, 5.2, 5.3 — Desp. Fin./EBITDA (3)
- 6.1, 6.2 — Fontes de Recursos (2)
- 7.1, 7.2 — Giro do Ativo (2)
- 8.1, 8.2 — Ciclo Financeiro (2)
- 9.1, 9.2, 9.3 — NCG absoluto (3)
- 10.1, 10.2 — NCG/Vendas (2)
- 11.1, 11.2 — PMC (2)
- 12.1, 12.2 — PME (2)
- 13.1, 13.2 — PMR (2)
- 14.1, 14.2 — Inadimplência (2)

**Total: 34 entradas (mínimo exigido pela STORY-026: 28).**

## Como verificar (testes derivados)

Para cada caso `indisponivel:*`, a STORY-028 cria um fixture em `app/tests/Domain/Motor/Fixtures/` e asserta:
- `valor === null`
- `farol === 'nenhum'`
- `motivo === '<código>'`
- `mensagem === '<texto padrão acima>'`

Total mínimo de testes derivados deste catálogo: **17 testes de "indisponibilidade"** (um por código de motivo), independentemente dos ≥ 10 testes por fórmula da DoD do épico.
