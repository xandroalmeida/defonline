---
story_id: STORY-003
slug: spike-persistencia
title: SPIKE — Decisão de persistência (migrations, multi-tenancy, audit, LGPD)
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

# STORY-003 — SPIKE de persistência

> **Para o agente Arquiteto:** spike → ADR `accepted`, sem código de produção. Esta ADR aprofunda a herança de PostgreSQL com decisões estruturais que afetam todos os agregados do domínio.

## Contexto

PostgreSQL como banco principal é herança técnica preservada pelo PDR-001 e ratificada em STORY-001 (Stack). Esta spike trata das **decisões estruturais sobre o uso de PostgreSQL** que não cabem na ADR de Stack: agregados do domínio em alto nível, estratégia de migrations, multi-tenancy (separação por Usuário/Empresa), audit log, soft vs hard delete (intersecção com LGPD direito de eliminação), extensões do Postgres a serem adotadas. Adicionar armazenamento extra além do Postgres exige justificativa forte (princípio #3 do Arquiteto — central).

- Épico: `epics/EPIC-000-foundation/epic.md`
- Documentos canônicos:
  - `defonline-docs/skills/arquiteto/references/architecture-principles.md` (princípio #3: Postgres-first)
  - `defonline-docs/skills/arquiteto/references/adr-types.md` — Tipo 4 Persistência (mini-checklist)
  - `defonline-docs/skills/arquiteto/references/diagrams.md` (diagrama ER obrigatório)
  - `defonline-docs/skills/arquiteto/references/security-architecture.md` (multi-tenancy, LGPD)
  - `defonline-docs/skills/arquiteto/templates/adr.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §1.5.2 (entidades Usuário, Empresa Analisada, Diagnóstico)
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §4, §5, §7 (LGPD)

## O quê

Produzir **ADR `proposed`** com `type: persistencia` decidindo: agregados do domínio em alto nível (Usuário, Empresa Analisada, Diagnóstico, Carteira, Plano, etc.); ferramenta de migrations + padrão (reversíveis, idempotentes, zero-downtime); mecanismo de multi-tenancy (coluna `usuario_id`/`empresa_id` + filtro automático no ORM, row-level security, ou outra estratégia); padrão de audit log (tabela append-only + triggers vs app-side); soft delete vs hard delete + interseção com LGPD; extensões do Postgres a serem adotadas (justificadas).

## Por quê

Sem decisão de persistência, STORY-001 (Stack) escolhe ORM no escuro, STORY-007 (hello world) não pode rodar migration, e EPIC-001 (Cadastro) não tem como persistir Usuário e Empresa. Multi-tenancy precisa ser nativa desde o início — retrofit é doloroso (`adr-types.md`).

## Critérios de aceite

- [ ] **CA-1:** ADR em `decisions/adr/ADR-XXX-persistencia.md`, `type: persistencia`.
- [ ] **CA-2:** ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 4: agregados identificados pela razão de negócio; diagrama ER ou de agregados; mecanismo de multi-tenancy + automação de filtro; ferramenta de migrations + padrão; estratégia de audit log; extensões do Postgres listadas e justificadas; soft vs hard delete + LGPD.
- [ ] **CA-3:** ADR contempla as três entidades de domínio canônicas da spec V2.5 §1.5.2: Usuário (CPF + email), Empresa Analisada (CNPJ ou CPF), e Diagnóstico (filho de Empresa Analisada). Não é necessário detalhar todos os 14 indicadores aqui — isso é decisão de implementação no EPIC-002.
- [ ] **CA-4:** Diagrama ER/agregados renderiza corretamente (Mermaid ou imagem na ADR).
- [ ] **CA-5:** Estratégia explícita para o direito de eliminação LGPD (NRF §7) — campo `deleted_at` (soft delete padrão) + job de purga (hard delete diferido).
- [ ] **CA-6:** Mínimo 2 alternativas reais + status quo nas decisões críticas (ex.: multi-tenancy via coluna + filtro vs row-level security do Postgres).
- [ ] **CA-7:** ADR submetida ao PO. `index.json` atualizado.

## Fora de escopo

- Stack/ORM específicos — STORY-001.
- Topologia macro — STORY-002.
- Detalhamento de todos os campos do quiz — decisão de implementação em EPIC-002.
- Backup/DR formais (entram quando produção real existir) — referência ao `nfr-architecture.md` é suficiente nesta ADR.

## Padrões de qualidade exigidos

Spike — mesma exceção declarada em STORY-001. Rigor da fundamentação da ADR mantido.

## Dependências

- **Bloqueada por:** nada (paralelizável com STORY-001, 002, 004, 005, 006).
- **Bloqueia:** STORY-007 (hello world rodar migration).
- **Acoplamento leve com STORY-001 (Stack):** o ORM da stack pode condicionar uma decisão local; em caso de conflito, o Arquiteto sinaliza nas duas spikes e decide qual prevalece.

## Decisões já tomadas

- PostgreSQL é banco principal (PDR-001 + STORY-001).
- Modelo de domínio Usuário → N Empresas Analisadas (espec V2.5 §1.5.2 + §6.1 RESOLVIDO).

## Liberdade técnica do agente Arquiteto

Você decide tudo de persistência que respeita os princípios. Você **não decide** stack, topologia, cloud, CI/CD, observabilidade — cada um em sua spike.

## Definição de Pronto

- [ ] CAs satisfeitos. ADR `proposed`. Diagrama renderiza. `index.json` atualizado. Notas preenchidas. `status: in_review`.

## Protocolo do agente

Siga `agent-task-format.md`. Igual à STORY-001.

## Notas do agente
(preenchido durante/após execução)
