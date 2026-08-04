---
sprint_id: SPRINT-2026-W32
wave: WAVE-2026-02
status: active
start_date: 2026-08-04
end_date: 2026-08-21
goal: "Zerar os débitos remanescentes da WAVE-2026-01 e destravar o EPIC-010 pelas três decisões que precedem qualquer código — sem que os dois esforços disputem a mesma pessoa."
duration_weeks: 2.5
created_at: 2026-08-04
created_by: po (Alexandro / Claude)
related_pdr: PDR-005
---

# SPRINT-2026-W32 — Limpeza da onda 1 + fundação do DRM

## Objetivo

Duas frentes que **não competem entre si**, porque exigem papéis diferentes.

A frente de **limpeza** fecha o que sobrou da onda 1: três estórias de conteúdo e bugfix do EPIC-002 que nunca saíram do `ready`, a verificação de deploy de duas que estão em `in_review`, e o item do feedback do Key User que é melhoria do relatório atual. Tudo isso é trabalho de **programador**.

A frente de **fundação** abre o EPIC-010 pelas três estórias que precedem qualquer implementação: duas decisões de arquitetura e o design das telas. Tudo isso é trabalho de **arquiteto** e **designer**.

Rodar as duas em paralelo é o que evita que as decisões de arquitetura do DRM virem caminho crítico depois — elas precisam de tempo para maturar, e não há motivo para começarem só quando a limpeza terminar.

## Estórias

### Frente 1 — Limpeza da WAVE-2026-01 (programador)

| ID | Título | Épico | Tipo | Tam. | Estado ao abrir |
|---|---|---|---|---|---|
| STORY-041 | Plural "ações" no veredito `em_alerta` | EPIC-002 | bugfix | XS | ready |
| STORY-042 | Pacote de conteúdo EBC (títulos, rótulos, glossário, NCG positivo) | EPIC-002 | implementation | M | ready |
| STORY-043 | Validação cruzada R4 — Q17.recebíveis ≥ Q09.venda_mensal | EPIC-002 | implementation | S | ready, dep. de 042 |
| STORY-044 | Valor nominal junto a cada indicador do relatório | EPIC-002 | implementation | S | ready |

Mais duas verificações, não estórias novas: **STORY-039 e STORY-040** estão em `in_review` com CI verde e testes completos desde 02/06, aguardando apenas confirmação de deploy em homologação. Fecham junto com o primeiro deploy desta sprint.

### Frente 2 — Fundação do EPIC-010 (arquiteto e designer)

| ID | Título | Papel | Tam. |
|---|---|---|---|
| STORY-045 | Spike — modelo de dados, versionamento e retificação de competência | arquiteto | M |
| STORY-046 | Specs de tela do módulo DRM | designer | L |
| STORY-053 | Spike — motor de exportação: planilha e PDF | arquiteto | M |

A `STORY-058` (spike de provisão de férias e 13º) **não entra** nesta sprint: depende da conclusão da `STORY-045` e pertence ao Checkpoint 3.

## Compromisso visível ao fim do sprint

- [ ] EPIC-002 sem nenhuma estória fora de `done`.
- [ ] Relatório de Diagnóstico exibindo valor nominal ao lado de cada indicador, em homologação — resposta ao item 1 do feedback do Key User.
- [ ] Decisão de arquitetura publicada e `accepted` sobre modelo de dados, versionamento e retificação do DRM.
- [ ] Decisão de arquitetura publicada e `accepted` sobre geração de planilha e PDF — servindo também ao EPIC-007.
- [ ] Três specs de tela do DRM em `ready`, revisados com o PO.

Cumpridos esses cinco itens, a `STORY-047` (lançamento mensal) pode abrir na sprint seguinte sem nenhuma decisão pendente à frente.

## Fora do sprint

- Qualquer implementação do DRM. Esta sprint só produz decisões e specs.
- `STORY-052` — segue bloqueada pelas faixas de farol por setor, que dependem da EBC.
- EPIC-003 — adiado pelo `PDR-005`, será decomposto depois do DRM.
- Beta com Robertos reais — desbloqueado pela aprovação da `STORY-036`, roda como frente de negócio em paralelo, fora do fluxo de sprint.

## Riscos

| Risco | Mitigação |
|---|---|
| As duas frentes disputarem a mesma pessoa | É o motivo de a divisão ser por papel. Se houver disputa real, a frente de limpeza tem prioridade — ela fecha uma onda |
| `STORY-045` concluir que a retificação exige modelo mais caro que o previsto | É exatamente para isso que ela vem antes do código. Descobrir agora é o resultado desejado, não um atraso |
| Sprint não fechar na data, repetindo o W26 | `PDR-005` item 5: sprint que não fecha é encerrado explicitamente com o resto transferido, nunca deixado `planned` |

## Fechamento do sprint

_A preencher pelo PO no encerramento._
