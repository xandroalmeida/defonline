---
story_id: STORY-002
slug: spike-topologia
title: SPIKE — Decisão topológica (estrutura macro do sistema)
epic_id: EPIC-000
sprint_id: null
type: spike
target_role: arquiteto
status: ready
owner_agent: null
created_at: 2026-05-20
updated_at: 2026-05-20
estimated_session_size: M
---

# STORY-002 — SPIKE de topologia

> **Para o agente Arquiteto:** leia esta estória por inteiro. Spike → ADR `accepted` ao final, sem código de produção.

## Contexto

A topologia do sistema decide se o DEFOnline nasce como monolito modular único (princípio #2 do `architecture-principles.md` — favorecido), separação técnica natural (FE/BE/worker), ou eventualmente microsserviços (improvável para o tamanho atual). A escolha afeta a borda do sistema, comunicação entre componentes, observabilidade e como tudo roda local (princípio #6). Esta decisão é independente da stack escolhida em STORY-001 — pode rodar em paralelo.

- Épico: `epics/EPIC-000-foundation/epic.md`
- Documentos canônicos:
  - `defonline-docs/skills/arquiteto/SKILL.md` e `architecture-principles.md`
  - `defonline-docs/skills/arquiteto/references/adr-types.md` — Tipo 2 Topológico (mini-checklist, exigências)
  - `defonline-docs/skills/arquiteto/references/diagrams.md` (diagrama Mermaid é obrigatório para esta ADR)
  - `defonline-docs/skills/arquiteto/templates/adr.md`
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` (panorâmico — regras de negócio preservadas)
  - `defonline-docs/skills/po/references/quality-standards.md` (cobertura E2E exige browser real)

## O quê

Produzir **ADR `proposed`** (a ser aceita pelo PO) com `type: topologico` decidindo: número e fronteira dos processos do sistema; comunicação entre eles (sync/async); plano de funcionamento local com Docker; estratégia de trace/correlação entre componentes (independente da ferramenta — a ferramenta vem da STORY-006 Observabilidade).

## Por quê

Sem topologia decidida, STORY-007 (hello world deploy) não sabe quantos containers subir nem como conectá-los. STORY-004 (Infra) não sabe quantos targets de deploy provisionar. Topologia clara também afeta a complexidade operacional aceita para um time pequeno.

## Critérios de aceite

- [ ] **CA-1:** ADR redigida em `decisions/adr/ADR-XXX-topologia.md` com `type: topologico`.
- [ ] **CA-2:** ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 2: componentes nomeados pela razão de negócio (princípio #5); diagrama Mermaid de topologia incluído; comunicação entre componentes especificada (sync/async + protocolo); justificativa explícita se foge do monolito; plano de funcionamento local com Docker; estratégia de trace/correlação entre componentes.
- [ ] **CA-3:** ADR apresenta no mínimo 2 alternativas reais + status quo (ex.: monolito modular vs separação FE/BE/worker vs microsserviços) com prós e contras.
- [ ] **CA-4:** Diagrama Mermaid renderiza corretamente (testar localmente).
- [ ] **CA-5:** ADR submetida ao PO para revisão. Status `proposed` → mudança para `accepted` é ato do PO.
- [ ] **CA-6:** `index.json` atualizado com a ADR.

## Fora de escopo

- Escolha de linguagem/framework — STORY-001.
- Modelo de dados detalhado — STORY-003.
- Provedor cloud específico — STORY-004.
- Detalhes de CI/CD — STORY-005.
- Ferramenta de observabilidade específica — STORY-006.

## Padrões de qualidade exigidos

Estória de spike — mesma exceção declarada em STORY-001: sem exigência de cobertura unitária/E2E sobre código de produção; rigor de fundamentação da ADR mantido; diagrama Mermaid validado renderizando.

## Dependências

- **Bloqueada por:** nada. Pode rodar em paralelo com STORY-001, 003, 004, 005, 006.
- **Bloqueia:** STORY-007.
- **Pré-requisitos de ambiente:** nenhum.

## Decisões já tomadas

- PDR-001 (escopo da onda).
- Princípio #2 (`architecture-principles.md`): favorecer monolito modular até evidência forte do contrário.

## Liberdade técnica do agente Arquiteto

Você decide a topologia desde que atenda aos princípios do Arquiteto. Você **não decide** stack, persistência, infra, CI/CD ou observabilidade aqui — cada um em sua spike.

## Definição de Pronto

- [ ] Todos os CAs satisfeitos. ADR `proposed`. Diagrama renderiza. `index.json` atualizado. "Notas do agente" preenchidas. Estória em `in_review`.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Igual à STORY-001.

## Notas do agente
(preenchido durante/após execução)
