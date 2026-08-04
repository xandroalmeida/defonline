---
story_id: STORY-051
slug: serie-historica-comparativos
title: "Série histórica e comparativos — o que mudou em relação ao mês passado e à média"
epic_id: EPIC-010
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-08-04
updated_at: 2026-08-04
estimated_session_size: L
checkpoint: CP2
---

# STORY-051 — Série histórica e comparativos

## Contexto

Um mês isolado diz pouco. "Sua margem foi 12%" não é acionável; "sua margem caiu de 18% para 12% porque o custo das mercadorias subiu 9 pontos" é. **É esta estória que transforma o DRM de planilha em produto** — e é o que justifica uma assinatura anual em vez de uma consulta avulsa.

- Épico: `epics/EPIC-010-drm-demonstracao-resultado-mensal/epic.md`
- Ler antes: spec da Tela 3 (STORY-046); `epics/EPIC-010-*/design/estrutura-drm.md` §5 e §6; `especificacao/V2/anexos/anexo-K-campos-lancamento-drm.md` §K.6 e §K.8; **`decisions/adr/ADR-007-modelo-dados-drm-versionamento-retificacao.md`** (§*Comparativos e faróis não são persistidos* e §*Para o time — impacto por estória*)

## O quê

Implementar a visão de série histórica das competências lançadas e os comparativos por linha da demonstração, incluindo os alertas que dependem de histórico.

## Por quê

É a diferença entre um usuário que lança uma vez e um que lança doze. Ataca diretamente a métrica primária do épico.

## Critérios de aceite

- [ ] **CA-1 (Lista de competências):** todas as competências da Empresa Analisada, ordenadas, com estados distintos e visíveis: lançada, retificada, **não lançada** e rascunho em aberto.
- [ ] **CA-2 (Ausência ≠ zero):** mês sem lançamento nunca é tratado como zero. Aparece como lacuna, com ação para lançá-lo. Regra dura — tratá-lo como zero produz quedas fantasma na série. **Mecânica do `ADR-007`:** ausência de lançamento é **ausência de linha** (não existe placeholder zerado nem estado `nao_lancado` no banco); a série gera a espinha de meses na leitura e faz `LEFT JOIN` com as versões vigentes; sem correspondente ⇒ `indisponivel:sem_lancamento`. **`COALESCE(…, 0)` é proibido em qualquer query de série** — é a forma exata como quedas fantasma entram num produto. Lançamento com `deleted_at` conta como ausente, nunca como zero.
- [ ] **CA-3 (Comparativos):** para cada linha de nível 1 **do modo vigente** (`PDR-004`, decisão B), os quatro comparativos de `design/estrutura-drm.md` §6 — variação vs. mês anterior, variação vs. média móvel de até 12 meses, acumulado no ano e participação média sobre a receita.
- [ ] **CA-4 (Indisponibilidade dos comparativos):** cada comparativo respeita suas condições de indisponibilidade (sem mês anterior; menos de 3 competências; janela inteira sem faturamento) com motivo semântico, nunca zero disfarçado.
- [ ] **CA-5 (Alerta A4 — rubrica atípica):** a partir do 4º lançamento, campo que exceda 3× sua própria média móvel é sinalizado no lançamento e marcado na série. Cobre férias e 13º (Anexo K §K.8). **A marcação precisa ser legível como explicação** — o usuário deve entender que a queda de dezembro tem causa conhecida, não que o negócio piorou.
- [ ] **CA-6 (Alerta A6 — salto de competência):** lacuna entre o último lançamento e o atual é detectada e oferece lançar o mês faltante.
- [ ] **CA-7 (Meses atípicos entram nos cálculos):** meses marcados como atípicos **não** são excluídos dos comparativos — são sinalizados. Omitir dado real é pior do que explicá-lo.
- [ ] **CA-8 (Granularidades mistas):** meses lançados em modo agregado e detalhado convivem na mesma série. Comparativo de item detalhado é indisponível nos meses agregados, com motivo `indisponivel:grupo_agregado`. Nunca inferir o item a partir do total. O modo de cada grupo está **carimbado no snapshot de cada mês** (`ADR-007`), não só na preferência atual da empresa — leia de lá. Como a estrutura de linhas de nível 1 é idêntica nos dois modos, **todo comparativo de nível 1 está sempre disponível**; só o item detalhado fica indisponível.
- [ ] **CA-9 (Retificação):** mês retificado é exibido como tal, e os comparativos que o usam como base refletem os valores retificados.
- [ ] **CA-10 (Consistência com o snapshot):** os comparativos derivam dos snapshots persistidos, não de recálculo do motor sobre lançamentos antigos.
- [ ] **CA-11 (Desempenho):** a série carrega em ≤ 2s p95 com 24 competências.
- [ ] **CA-12 (Mobile-first):** utilizável em 360×800 sem scroll horizontal.
- [ ] **CA-13 (Testes):** unitários de cada comparativo, incluindo todas as condições de indisponibilidade e granularidade mista; feature cobrindo série vazia, 1, 3, 4 e 13 competências, série com lacuna, série com mês retificado; E2E percorrendo dois lançamentos consecutivos e conferindo o comparativo.
- [ ] **CA-14 (Cobertura):** ≥ 80%; ≥ 98% no cálculo dos comparativos.

## Fora de escopo

- Gráficos, salvo se o spec da STORY-046 os justificar.
- Projeção, tendência ou previsão de meses futuros.
- Comparação com outras empresas ou benchmark de mercado.
- Exportação da série (STORY-055).

## Sobre rubricas sazonais (decisão D)

`[DECIDIR-D]` foi respondida em 04/08/2026: a EBC quer **provisão contábil completa**. O `PDR-004` registra a decisão e explica por que ela não cabe como parâmetro — provisão exige um saldo acumulado entre meses, que é conta patrimonial, e o DRM é resultado em caixa puro por desenho. O caminho está sob spike na `STORY-058`.

**Esta estória entrega o tratamento vigente**, que responde à dor concreta sem nenhuma conta patrimonial: alerta A4 detecta a rubrica atípica, a série marca o mês, o comparativo explica. Se o spike aprovar a provisão dentro do épico, ela vem como estória adicional — não altera o que está aqui.

## Padrões de qualidade exigidos

`defonline-docs/skills/po/references/quality-standards.md`.

## Dependências

- **Bloqueada por:** STORY-046, STORY-048, STORY-049, STORY-050.
- **Bloqueia:** STORY-054 (a leitura em prosa usa os comparativos), STORY-055.

## Decisões já tomadas (não as reabra)

- `design/estrutura-drm.md` §5 e §6 — quais comparativos e suas condições de indisponibilidade.
- Ausência ≠ zero; meses atípicos entram nos cálculos.
- **ADR-007** (decisão da STORY-045). Em particular: **comparativos não são persistidos** — são derivados na leitura sobre as versões vigentes (CA-10 sai daí, e é o que faz o CA-5 da STORY-050 ser impossível de violar). A marca de mês atípico (A4) também é derivada, não coluna. Mês retificado é `versao > 1`. Se o CA-11 (2s p95) apertar com volume real, a saída registrada na ADR é cache de leitura invalidável por `(empresa, competência)` — reversível e local, nunca persistir comparativo.

## Definição de Pronto

- [ ] CA-1 a CA-14 passam.
- [ ] **Checkpoint 2 demonstrado em homologação:** lançar dois meses, retificar o primeiro, ver o comparativo do segundo se ajustar.
- [ ] Pipeline verde; `index.json` atualizado; notas preenchidas.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`.

## Notas do agente

### Decisões tomadas
### Descobertas
### Bloqueios encontrados
### IDRs criados
### Cobertura final
