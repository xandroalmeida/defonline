---
epic_id: EPIC-010
type: design-note
title: Estrutura canônica da Demonstração de Resultado Mensal
version: 1.1
created_at: 2026-08-04
updated_at: 2026-08-04
owner_role: po
fonte: "Demonstração de Resultado Mensal - DRM.xlsx (Key User José Marques, 03/08/2026)"
decisoes: [PDR-003, PDR-004]
---

# Estrutura canônica do DRM

Referência aritmética única do módulo. Nomes de campo (`Dnn`) definidos em `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md`. Toda linha calculada aqui deve ter teste unitário dedicado no motor.

> **v1.1 (04/08/2026)** — incorpora o `PDR-004`: dois modos de apresentação do pró-labore, linhas não operacionais aprovadas, percentuais em todas as linhas e rótulos finais.

## 1. Linhas da demonstração

A demonstração tem **dois modos de apresentação**, escolhidos por Empresa Analisada e alternáveis a qualquer momento (`PDR-004`, decisão B). O modo é **escolha de exibição, não de cálculo**: não altera o lançamento, não recalcula nada e não quebra a série histórica.

Nível 1 = totalizadores; nível 2 = subtotais de grupo; nível 3 = itens detalhados, exibidos só quando o grupo está em modo detalhado.

### 1.1 Modo "pró-labore fora" — **default**

Como o Key User desenhou. O dono vê "quanto sobrou depois de eu me pagar" como linha destacada.

| # | Nív. | Linha | Fórmula | % da receita |
|---|---|---|---|---|
| L1 | 1 | Receita Bruta | `D01` | 100% (âncora) |
| L2 | 1 | (–) Deduções da Receita Bruta | `D02` | sim |
| L3 | 1 | **= Receita Operacional Líquida** | `L1 − L2` | sim |
| L4 | 1 | (–) Custo do que foi vendido | `D03` ou `D04+D05+D06+D07` | sim |
| L5 | 1 | **= Lucro Bruto (Margem Bruta)** | `L3 − L4` | sim |
| L6 | 1 | (–) Despesas Operacionais | `L6a + L6b` | sim |
| L6a | 2 | Despesas com Vendas | `D08` ou `Σ(D09..D14)` | sim |
| L6b | 2 | Despesas Administrativas e Gerais | `D15` ou `Σ(D16..D36)` | sim |
| L7 | 1 | **= Resultado Operacional** | `L5 − L6` | sim |
| L7b | 1 | (±) Outras receitas e despesas | `D41 − D42` | sim |
| L8 | 1 | (–) Despesas Financeiras | `D37` ou `D38+D39` | sim |
| L8a | 2 | Juros pagos | `D38` | sim |
| L8b | 2 | Tarifas bancárias | `D39` | sim |
| L9 | 1 | **= Resultado antes do pró-labore** | `L7 + L7b − L8` | sim |
| L10 | 1 | (–) Pró-labore | `D40` | sim |
| L11 | 1 | **= Lucro Líquido do Mês (Margem Líquida)** | `L9 − L10` | sim |

**Neste modo, L7 não se chama EBITDA** — porque não é. Ver §3.1.

### 1.2 Modo "pró-labore dentro"

Definição contábil padrão. O Resultado Operacional é o EBITDA de verdade, coerente com o relatório de Diagnóstico.

| # | Nív. | Linha | Fórmula | % da receita |
|---|---|---|---|---|
| L1 – L5 | | idênticas ao modo "fora" | | |
| L6 | 1 | (–) Despesas Operacionais | `L6a + L6b` | sim |
| L6a | 2 | Despesas com Vendas | `D08` ou `Σ(D09..D14)` | sim |
| L6b | 2 | Despesas Administrativas e Gerais | (`D15` ou `Σ(D16..D36)`) **+ `D40`** | sim |
| L6c | 3 | Pró-labore | `D40` | sim |
| L7 | 1 | **= Resultado Operacional (EBITDA)** | `L5 − L6` | sim |
| L7b | 1 | (±) Outras receitas e despesas | `D41 − D42` | sim |
| L8 | 1 | (–) Despesas Financeiras | `D37` ou `D38+D39` | sim |
| L11 | 1 | **= Lucro Líquido do Mês (Margem Líquida)** | `L7 + L7b − L8` | sim |

**L9 e L10 não existem neste modo** — colapsam no Lucro Líquido, porque o pró-labore já foi deduzido em L6b. O pró-labore aparece como item de nível 3 (L6c) quando o grupo administrativo está em modo detalhado.

### 1.3 Invariante entre os modos

> **O Lucro Líquido do Mês (L11) é idêntico nos dois modos.** Só as linhas intermediárias mudam.

Verificado em 2000 casos aleatórios. **Este invariante é obrigatório e deve ter teste dedicado no motor** — se alternar o modo mudar o lucro do mês, é bug grave: a resposta à pergunta que dá nome ao módulo passaria a depender de uma preferência de exibição.

Consequências práticas:

- Alternar o modo **não recalcula** nada — ambos os conjuntos de valores derivam dos mesmos campos persistidos.
- Meses antigos são **reexibidos** no modo novo, sem migração e sem quebra de comparabilidade da série.
- Não existe "em que modo este mês foi lançado". O modo não afeta o lançamento.
- O modo vigente é exibido junto à demonstração, para o usuário saber qual leitura está vendo.

## 2. Coluna de percentuais

Todo percentual é sobre a **Receita Bruta (L1)**, seguindo a planilha original (`=D<n>/D$2*100`).

- Exibido em **todas** as linhas, incluindo L2 e L3, que a planilha original não trazia (`PDR-004`, decisão C — resolve F3).
- L1 é ancorada em 100%.
- Aritmética exata; arredondamento só na apresentação. Exibição com 1 casa decimal.
- **Quando `L1 = 0`:** todos os percentuais recebem `null` com motivo `indisponivel:sem_faturamento`. Os valores nominais continuam sendo exibidos. Ver Anexo K §K.7.

## 3. Divergências deliberadas em relação à planilha original

Três, todas resolvidas pelo `PDR-004`.

**3.1 — Rótulo da linha L7 (resolvido).** A planilha rotula `A43` como *"Resultado Operacional (M.Operacional / Ebitda)"*. Como o pró-labore é subtraído depois, esse valor **não é o EBITDA na definição padrão**. A parametrização resolve: no modo "fora", a linha é **"Resultado Operacional"**, sem menção a EBITDA; no modo "dentro", é **"Resultado Operacional (EBITDA)"**, e aí o nome é correto. Quem quiser reconciliar DRM e Diagnóstico liga o modo "dentro".

**3.2 — Rótulo da linha L9 (resolvido).** A planilha rotula `A47` como *"Resultado Antes do IR/CSLL"*. O optante do Simples não recolhe IR e CSLL separadamente — ambos estão na guia única já lançada em `D02`. Rótulo final: **"Resultado antes do pró-labore"**, que é literalmente o que a linha é. Existe apenas no modo "fora".

**3.3 — Linhas não operacionais (resolvido).** `D41` e `D42` aprovados (`PDR-004`, decisão E). Posicionados **após** o Resultado Operacional, para não contaminar as margens operacionais. **A linha L7b é omitida quando ambos os campos são zero** — não exibir linha zerada.

## 4. Regras de indisponibilidade

Mesmo padrão do motor de Diagnóstico: valor `null` + motivo semântico, nunca exceção e nunca zero disfarçado.

| Condição | Efeito | Motivo |
|---|---|---|
| `L1 = 0` | Toda a coluna de percentuais indisponível | `indisponivel:sem_faturamento` |
| Grupo em modo agregado | Itens do grupo indisponíveis na série histórica | `indisponivel:grupo_agregado` |
| Competência sem lançamento | Ausente da série (≠ zerada) | `indisponivel:sem_lancamento` |

A distinção entre **mês sem lançamento** e **mês sem movimento** é obrigatória. Tratar ausência como zero produziria quedas fantasma na série histórica.

## 5. Comparativos da série histórica

Calculados sobre lançamentos fechados da mesma Empresa Analisada, para cada linha de nível 1 do modo vigente:

| Comparativo | Base | Indisponível quando |
|---|---|---|
| Variação vs. mês anterior | competência imediatamente anterior | não existe lançamento no mês anterior |
| Variação vs. média móvel | média das competências anteriores disponíveis, até 12 | menos de 3 competências anteriores |
| Acumulado no ano | soma das competências do mesmo ano-calendário | sempre disponível a partir do 1º lançamento |
| Participação média | média dos percentuais sobre receita | todos os meses da janela sem faturamento |

Meses marcados como atípicos (alerta A4 do Anexo K) **entram** nos cálculos, mas são sinalizados na tela — omitir dado real seria pior do que explicá-lo.

## 6. Rubricas sazonais — tratamento vigente

`PDR-004`, decisão D, fixou a **provisão contábil completa** como alvo, com a ressalva de que ela exige uma conta patrimonial que o DRM não tem. Enquanto o spike da `STORY-058` não conclui, vale:

- Entrada sempre em regime de caixa (valor efetivamente pago no mês).
- Alerta A4 detecta a rubrica atípica; a série marca o mês; o comparativo explica.
- Nenhum saldo acumulado entre meses, nenhuma conta patrimonial.

Se o spike aprovar a provisão dentro deste épico, esta seção é substituída e a versão do motor recebe bump.

## 7. Pureza e determinismo

O motor do DRM segue as mesmas regras duras do motor de Diagnóstico, verificadas por teste arquitetural:

- Sem `now()`, sem `Auth`, sem leitura de banco. Competência e carimbo de tempo entram como parâmetro.
- Aritmética exata (não ponto flutuante). Arredondamento apenas na formatação.
- Ordem das linhas é fixa e faz parte do contrato — alterá-la exige bump de versão do motor.
- **O modo de apresentação não entra no cálculo persistido.** O snapshot guarda os valores que permitem derivar os dois modos; a escolha do modo é aplicada na leitura.
- Saída persistida como snapshot versionado, junto com a versão do motor que a produziu.
