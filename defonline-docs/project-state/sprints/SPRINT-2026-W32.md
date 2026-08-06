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

- [~] EPIC-002 sem nenhuma estória fora de `done`. **Todas as estórias entregues**; a `STORY-060` está em `in_review` aguardando o deploy `v0.12.0-rc.1`. O épico é promovido no ato da verificação em homologação.
- [x] Relatório de Diagnóstico exibindo valor nominal ao lado de cada indicador, em homologação — `STORY-044`, entregue no `v0.11.0-rc.1`.
- [x] Decisão de arquitetura publicada e `accepted` sobre modelo de dados, versionamento e retificação do DRM — `ADR-007`.
- [x] Decisão de arquitetura publicada e `accepted` sobre geração de planilha e PDF — `ADR-008`.
- [x] Três specs de tela do DRM em `ready`, revisados com o PO — `STORY-046`, aprovadas no `PDR-007`.

Quatro dos cinco cumpridos; o quinto depende só do deploy. A `STORY-047` (lançamento mensal) pode abrir na sprint seguinte **sem nenhuma decisão pendente à frente** — que era o objetivo real da lista.

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

**Encerrado em 2026-08-06 pelo PO.** Dez estórias planejadas, **dez entregues** — as três últimas (`STORY-052`, `STORY-059`, `STORY-060`) em `in_review`, aguardando apenas o deploy `v0.12.0-rc.1`. Nenhuma estória transferida.

### O que a sprint entregou além do previsto

O objetivo era "zerar os débitos da onda 1 e destravar o EPIC-010 pelas três decisões que precedem qualquer código". Isso foi feito. O que não estava no plano e apareceu:

- **Um bug sério de produção** (`BUG-001`, máscara monetária perdendo o último dígito — a tela mostrava `R$ 150.000,00` e chegava `R$ 15.000,00` ao motor), descoberto durante a `STORY-043` e corrigido no mesmo release.
- **Três escalações decididas** (`PDR-006`) e **quatro resíduos da fundação do DRM** (`PDR-008`), mais os defaults de conteúdo e o nome comercial (`PDR-009`). O `[DECIDIR-A]`, aberto desde o `PDR-003`, fechou: o módulo se chama **"Raio X do Negócio"**.
- **Dois DDRs de design system** (`DDR-006`, `DDR-007`) e o **DS canônico em v1.1** — os primeiros tokens novos desde a promoção a v1, e nenhum deles veio de tela querendo cor: vieram de medição de contraste que reprovava.
- **Gate de acessibilidade de 85 para 100** na página do relatório.

### O que a sprint ensinou, e vale para a próxima

- **"Mudei só um texto" alterou o motor três vezes em três meses** (STORY-041, STORY-060 e a revisão de redação da própria 060). O `IDR-010` acumulou três aditivos dizendo a mesma coisa. Se acontecer uma quarta, o problema deixou de ser esquecimento e virou processo.
- **A conferência de golden hash por reconstrução** — desfazer só o texto e verificar que os hashes voltam bit a bit — provou o que "mudou/não mudou" não prova. Vale promover a padrão de re-emit.
- **O gate achou o que a conta em papel não achava.** O contraste calculado dava 4,76:1 e o Lighthouse encontrou um nó em 4,2:1: dois fundos translúcidos empilhados. Medição em ferramenta e medição em planilha não se substituem.
- **A validação pegou uma regressão que os testes não pegavam.** O pill de farol cresceu 2px e passou por todos os testes, porque eles mediam contraste e semântica — não geometria. Captura de tela também não denuncia 2px.
- **Revisão editorial do PO derrubou uma redação já "pronta"**, e estava certa: "folga operacional" era lido como "sobra", quando o número dizia o oposto. O protocolo que manda sinalizar texto ao PO antes do merge se pagou nesta sprint.

### Resíduos que viraram estória

Nenhum foi varrido para debaixo do tapete:

| Estória | Origem | O que é |
|---|---|---|
| `STORY-061` | achado da `STORY-059` | O gate do `IDR-008` é cego a `rgba()` — o badge violava e passava |
| `STORY-062` | achado da `STORY-052` | O inventário de lacunas nunca escreve em ambiente local, nos dois mecanismos |

### Pendências no encerramento

Uma só: **o deploy `v0.12.0-rc.1`** e a verificação em homologação das três estórias em `in_review`. Com ela, o `EPIC-002` é promovido a `done`.

### Relatório de validação

`sprints/SPRINT-2026-W32-validacao.md` — veredito `approved_with_pending` na rodada 2, depois de reprovar a rodada 1 com dois fails não-bloqueantes, ambos corrigidos.
