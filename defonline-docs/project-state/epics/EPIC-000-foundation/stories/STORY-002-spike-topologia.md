---
story_id: STORY-002
slug: spike-topologia
title: SPIKE — Decisão topológica (estrutura macro do sistema)
epic_id: EPIC-000
sprint_id: null
type: spike
target_role: arquiteto
status: done
owner_agent: arquiteto (claude-opus-4-7)
created_at: 2026-05-20
updated_at: 2026-05-21
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

**Sessão:** 2026-05-21 — Arquiteto (claude-opus-4-7).

### Resumo da execução

1. Li a estória inteira + ADR-001 (input fixado) + `arquiteto/SKILL.md` + `references/adr-types.md` (Tipo 2 mini-checklist) + `references/diagrams.md` + `templates/adr.md` + protocolo `agent-task-format.md`.
2. Mapeei via leitura panorâmica da `especificacao-funcional.md` + `requisitos-nao-funcionais-e-juridicos.md` + épicos da WAVE-2026-01 os **fluxos que não cabem no request HTTP** (PDF, e-mail, expiração de rascunho, retenção 12m + aviso D-7, anonimização LGPD). Eles **forçam** worker no MVP — não é opcional.
3. Apresentei 4 opções ao PO (A monolito+worker+scheduler / B Octane/FrankenPHP / C BE API + FE SPA / D microsserviços) e confirmei direção via `AskUserQuestion` antes de redigir a ADR (princípio do `SKILL.md`: opções primeiro, formalização depois). PO escolheu **Opção A** e confirmou Mailpit no compose desde a STORY-007.
4. Redigi `ADR-002-topologia.md` em `status: proposed`. Cobre integralmente o mini-checklist do `adr-types.md` Tipo 2:
   - ✅ Componentes nomeados pela razão de negócio (auth, cadastro, diagnostico, historico, lgpd) — princípio #5.
   - ✅ Diagrama Mermaid `flowchart LR` com subgraph do Docker Compose, externos com classe `external`, mailpit com classe `devonly`.
   - ✅ Comunicação especificada (tabela inteira em "Comunicação entre componentes") com sync/async + protocolo (HTTPS, SQL, SMTP, `database` driver de queue).
   - ✅ Justificativa explícita de **não** fugir do monolito (princípio #2) + caminho de promover worker a serviço se dor concreta aparecer.
   - ✅ Plano local Docker (`docker-compose.yml` conceitual com 5 serviços; lição aprendida do bind-mount inode aplicada via volume nomeado).
   - ✅ Estratégia de trace/correlação independente de ferramenta: UUID v7 `request_id` propagado por middleware no `web` + payload de job nos `worker`/`scheduler`; ferramenta concreta fica para STORY-006.
5. Validei o diagrama Mermaid renderizando com `@mermaid-js/mermaid-cli` (SVG de 25KB gerado sem erros) — **CA-4 satisfeito**.
6. Atualizei `index.json` com entrada `ADR-002` em `decisions.adr[]` (status `proposed`, related_adrs/epics/stories preenchidos) — **CA-6 satisfeito**.

### CA → evidência

- **CA-1** ✅ `defonline-docs/project-state/decisions/adr/ADR-002-topologia.md` com `type: topologico`.
- **CA-2** ✅ Mini-checklist Tipo 2 coberto integralmente (ver detalhamento acima).
- **CA-3** ✅ 4 opções analisadas (A, B, C, D) com prós/contras explícitos + matriz comparativa de 10 critérios. C e D rejeitadas com motivo registrado.
- **CA-4** ✅ Diagrama renderizado localmente com `mmdc` (SVG válido 25KB). Sintaxe testada.
- **CA-5** ✅ ADR em `status: proposed`. Aguarda aceite do PO no chat. **A mudança para `accepted` é ato do PO**, conforme regra do `adr-lifecycle.md` e diretiva clara da SKILL ("nunca marca `accepted` sem aprovação humana explícita").
- **CA-6** ✅ `index.json` atualizado.

### Decisões deixadas explicitamente para outras spikes / IDRs

- Provedor SMTP de produção, ferramenta de log/trace, reverse proxy/TLS, storage de PDFs → ADRs futuras.
- Layout exato de pastas e namespaces dos módulos, convenção de payload de job, linter custom de fronteira de módulo → IDR do Programador na STORY-007.

### Próximos passos esperados

1. **PO revisa** ADR-002, aprova/ajusta/rejeita no chat.
2. Se aceita: PO atualiza frontmatter da ADR (`status: accepted`, `decided_at`, `approved_by`) e a estória vira `done`. Em paralelo, o `index.json` também é atualizado para `status: accepted`.
3. STORY-007 (hello world deployado) recebe esta ADR como input topológico — o "hello world" deve incluir um job assíncrono validando os 3 processos + propagação de `request_id` + Mailpit.

### Nenhum bloqueio. Nenhum [ESCALONAMENTO].

### Encerramento

- 2026-05-21 — PO Alexandro aprovou ADR-002 em chat. ADR transicionada para `accepted`; `index.json` atualizado; estória movida para `done`.
