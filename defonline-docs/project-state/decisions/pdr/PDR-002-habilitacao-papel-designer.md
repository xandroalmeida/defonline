---
pdr_id: PDR-002
slug: habilitacao-papel-designer
title: Habilitação do papel Designer no método DEFOnline (5º papel) e evolução do schema do índice
status: accepted
decided_at: 2026-05-24
decided_by: PO (Alexandro / Claude)
approved_by: Alexandro
approved_at: 2026-05-24
supersedes: null
superseded_by: null
related_epics: ["EPIC-001", "EPIC-002", "EPIC-003"]
related_adrs: ["ADR-001"]
---

# PDR-002 — Habilitação do papel Designer no método DEFOnline

## Contexto

O método do DEFOnline tem hoje 4 papéis: PO, Arquiteto, Programador, Validador. A skill `designer/` foi acabada de criar (12 arquivos, ~2.380 linhas) e descreve um Designer de Produto que decide UX/UI dentro do que o PO especificou, mantém um Design System vivo (herdando do canônico em `especificacao/V2/design-system.md`), registra decisões de design durável como DDRs e produz specs de tela mobile-first. O Designer trabalha em **paralelo** com o Programador na mesma estória.

A skill `designer/` está pronta no nível de doutrina, mas **não funciona** no método atual porque depende de gatilhos e estruturas que ainda não existem:

- O enum `target_role` em `po/templates/story.md` e `po/references/agent-task-format.md` é `programador | arquiteto | validador` — **não inclui `designer`**.
- O frontmatter de estória não tem o campo `requires_design` que sinaliza paralelismo Designer↔Programador.
- O schema do `index.json` (`po/references/indexing.md`) só prevê `decisions.{pdr,adr,idr}` — não tem `decisions.ddr[]` nem `design.screens[]`.
- O glossário (`po/references/glossary.md`) lista PDR/ADR/IDR mas **não DDR**.

Sem essas evoluções, a skill `designer/` descreve um workflow que o método não suporta. Esta decisão habilita o papel oficialmente, evoluindo os 4 artefatos do PO em uma única operação coordenada.

A decisão precisa ser tomada **agora** porque o EPIC-002 (Diagnóstico) e o EPIC-004 (App Shell e Navegação) já planejados na WAVE-2026-01 têm estórias com forte componente de UI/UX que se beneficiariam do Designer atuando em paralelo desde o início.

## Opções consideradas

### Opção 1 — Habilitação completa: novo papel + enum + campo + schema + glossário, tudo agora

- **Descrição:** adicionar `designer` ao enum `target_role`; introduzir campo opcional `requires_design: bool` no frontmatter de estória; adicionar `decisions.ddr[]` e `design.screens[]` ao schema do `index.json` (bumpando `version` para 2); adicionar DDR e Designer ao glossário. Quatro arquivos do PO mudam (`templates/story.md`, `references/agent-task-format.md`, `references/indexing.md`, `references/glossary.md`) e o `index.json` ganha as novas seções (vazias).
- **Prós:**
  - Skill `designer/` deixa de ser doutrina teórica e vira operacional.
  - Habilitação consistente: um só PDR documenta a coordenação inteira.
  - EPIC-002 e EPIC-004 podem ser planejados já considerando o Designer no paralelismo.
  - Schema do índice fica pronto antes do primeiro DDR/spec — Designer não bloqueia no primeiro uso.
- **Contras:**
  - Bump de `version` do schema (`1 → 2`) — agentes que cacheiam interpretação precisam reler `indexing.md`.
  - Adiciona complexidade ao método (5 papéis em vez de 4). O time é muito pequeno (1 pessoa + IA); risco real de "papel decorativo" que ninguém usa de fato.
  - Mais um conjunto de decisões para o humano aprovar (DDRs) — atrito a mais.

### Opção 2 — Habilitação mínima: só enum + glossário, sem schema do índice e sem `requires_design`

- **Descrição:** adicionar `designer` ao enum e ao glossário. **Não** mexer no schema do índice nem no frontmatter. Designer trabalharia sem registro no índice (DDRs e specs em arquivos `.md`, mas sem entrada queryable). O paralelismo Designer↔Programador continuaria informal (acordo no chat).
- **Prós:**
  - Mudança menor — menos arquivos tocados, sem bump de version.
  - Permite testar o papel "na prática" antes de cristalizar schema.
- **Contras:**
  - Quebra o princípio "estado registrado, sempre" do PO — DDRs e specs ficam invisíveis para o índice.
  - Repete o problema que motivou este PDR: skill descreve gatilhos que não funcionam.
  - Adia trabalho que vai ser feito de qualquer jeito assim que o primeiro DDR for criado — não é simplificação, é postergação.

### Opção 3 — Status quo: não habilitar o Designer agora

- **Descrição:** manter o método como está (4 papéis). A skill `designer/` fica como documento de referência futura, **não** como papel ativo. Decisões de UX no curto prazo continuam tomadas por PO (quando afetam comportamento) ou pelo Programador (quando puramente visuais), dentro do DS da especificação.
- **Prós:**
  - Zero mudança no método. Menos peças móveis.
  - Time pequeno: um papel a menos para gerenciar.
- **Contras:**
  - Skill `designer/` recém-criada (12 arquivos, 2.380 linhas de doutrina) vira documento morto.
  - Decisões de design durável continuam sem lugar formal (espalhadas em comentários de PR e conversas).
  - O DS da especificação não tem mantenedor explícito — vai apodrecer.
  - EPIC-002/EPIC-004 perdem a oportunidade de design dedicado em paralelo.

## Decisão

> **Optamos pela Opção 1 — Habilitação completa.**

O Designer entra como **5º papel** do método DEFOnline, com habilitação completa em um único movimento coordenado: enum, campo de frontmatter, schema do índice e glossário evoluem juntos neste PDR.

## Justificativa

A skill `designer/` foi criada com escopo claro e fronteiras bem definidas (não invade PO/Arquiteto/Programador/Validador). A auditoria independente confirmou que os bloqueios são todos de **coordenação** com o PO — exatamente o que este PDR resolve. As alternativas falham:

- **Opção 2** (habilitação parcial) viola o princípio "estado registrado, sempre" — DDRs e specs sem entrada no índice ficam invisíveis e o projeto deriva.
- **Opção 3** (status quo) descarta investimento já feito e deixa o DS da especificação sem mantenedor, garantindo apodrecimento.

A complexidade adicional do 5º papel é mitigada por:

1. Em time pequeno, **a mesma pessoa atua em múltiplos papéis** em momentos diferentes (regra "papel vs pessoa" já estabelecida no `po/SKILL.md`). Adicionar Designer não exige contratar Designer — exige declarar o ato.
2. O Designer só é acionado quando a estória tem `requires_design: true` ou `target_role: designer`. Estórias sem UI nova continuam fluindo sem o papel — sobrecarga zero.
3. DS já existe na especificação. Designer não cria do zero — herda e evolui.

Conexão com o norte e a visão:

- **Visão de produto:** profissional MPE precisa de interface profissional. DS sem mantenedor explícito → drift visual → percepção amadora.
- **North star** (MPEs ativas com ≥1 diagnóstico em 90 dias): qualidade da experiência mobile é determinante; Designer com paralelismo cedo evita retrabalho que atrasaria entrega.
- **Princípio "qualidade é requisito, não negociação"**: design coerente é dimensão de qualidade tanto quanto cobertura de testes.

## Consequências

### Positivas

- Skill `designer/` operacional. EPIC-002 e EPIC-004 podem usar `requires_design: true` desde o planejamento.
- DS canônico ganha mantenedor explícito (Designer evolui via `project-state/design/system/` herdado de `especificacao/V2/design-system.md`).
- Decisões de UX durável ganham forma versionada (DDR) com aprovação humana, análoga a ADR/PDR/IDR.
- Schema do índice fica pronto para queries tipo "qual o status de design do EPIC-X?".
- O paralelismo Designer↔Programador documentado em `collaboration-with-developer.md` ganha gatilho real.

### Negativas / trade-offs aceitos

- `version` do schema do índice passa de `1` para `2`. Agentes/queries existentes que dependam de schema 1 precisam ser atualizados; o bump fica documentado neste PDR e nas notas do `indexing.md`.
- Mais um tipo de decisão para o humano aprovar (DDRs). Mitigação: agrupamento de DDRs propostos relacionados em uma única sessão de aprovação (etiqueta já descrita em `designer/references/ddr-lifecycle.md`).
- Risco de "Designer carimbador" — atuação raso, virando overhead. Mitigação: Designer **só** atua em estória com `requires_design: true` ou `target_role: designer`; estórias sem UI nova seguem o fluxo de 4 papéis original.

### Para o time técnico

- ADRs que esta decisão pode demandar: nenhuma. Stack já vigente (ADR-001) suporta a coexistência sem mudança.
- Impacto em épicos:
  - **EPIC-001 (Cadastro mínimo)** — já em `ready`/`approved_with_pending`; **não retroagimos**. Designer não revisita estórias deste épico salvo se a pendência envolver UI.
  - **EPIC-002 (Diagnóstico)** — `draft`. Planejamento futuro inclui Designer em paralelo nas estórias de UI.
  - **EPIC-003 (Histórico)** — `draft`. Idem.
  - **EPIC-004 (App Shell e Navegação)** — caso típico: provavelmente terá várias estórias com `requires_design: true`.

### Para a skill `designer/`

- A seção "Dependências de habilitação" da `designer/SKILL.md` (linhas ~125–135) passa a estar **satisfeita**. Os gatilhos descritos (`target_role: designer`, `requires_design: true`, seções `decisions.ddr[]` e `design.screens[]` no índice) ficam operacionais a partir do `accepted` deste PDR.

## Sinais de revisão

Este PDR deve ser reconsiderado se:

- Após 2 épicos de UI usando o Designer, ele tiver criado **menos de 3 DDRs** e **menos de 5 screen specs** — sinal de papel decorativo. Reavalia se o paralelismo está dando valor ou só atrito.
- Se o time crescer e for contratado Designer dedicado — pode justificar enriquecer o papel (ex.: cycle de pesquisa com usuário, métricas de usabilidade) que hoje estão fora de escopo.
- Se a stack do front mudar (ADR de FE diferente de Laravel+Livewire+Blade), revisar se o paralelismo Designer↔Programador continua fazendo sentido na nova stack.
- Se houver fricção real e medida no workflow paralelo (estórias atrasando por sync Designer↔Programador), reavaliar para modelo sequencial.

## Mudanças concretas que este PDR autoriza (escopo da execução)

1. `defonline-docs/skills/po/templates/story.md` — enum `target_role` passa a `programador | arquiteto | validador | designer`. Adiciona campo opcional `requires_design: false` ao frontmatter padrão.
2. `defonline-docs/skills/po/references/agent-task-format.md` — roteamento de skill por `target_role` inclui `designer`. Acrescenta nota sobre paralelismo Designer↔Programador.
3. `defonline-docs/skills/po/references/indexing.md` — bump `version` para `2`. Adiciona seções `decisions.ddr[]` e `design.screens[]` ao schema, com invariantes correspondentes. Documenta a migração 1→2 neste PDR.
4. `defonline-docs/skills/po/references/glossary.md` — adiciona entradas **DDR (Design Decision Record)** e **Designer**.
5. `defonline-docs/project-state/index.json` — bump `version: 2`, adiciona `decisions.ddr: []` e `design: { screens: [] }`, registra este PDR-002 em `decisions.pdr[]`.
