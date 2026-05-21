---
story_id: STORY-003
slug: spike-persistencia
title: SPIKE — Decisão de persistência (migrations, multi-tenancy, audit, LGPD)
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

**Sessão:** 2026-05-21 — Arquiteto (claude-opus-4-7).

### Resumo da execução

1. Li a estória inteira + `adr-types.md` Tipo 4 (mini-checklist Persistência) + `diagrams.md` (diagrama ER obrigatório) + `templates/adr.md` + `architecture-principles.md` (#3 Postgres-first).
2. Mapeei via leitura panorâmica da especificação V2.5 e dos épicos da WAVE-2026-01 os **agregados canônicos** (Usuario root, EmpresaAnalisada 1:N, Quiz, Diagnostico, Subscription, TermAcceptance, AuditLog) + restrições de LGPD §7, audit log §5, retenção 12 meses do diagnóstico §4.8, expiração 90d do rascunho §6.4.
3. Apresentei 2 perguntas críticas ao PO via `AskUserQuestion` antes de redigir a ADR (princípio do `SKILL.md`: opções primeiro, formalização depois):
   - **Multi-tenancy:** PO escolheu **FK + Global Scope + Policy** (rejeitado RLS no MVP por excesso de cerimônia em time pequeno).
   - **Criptografia at-rest do CPF:** PO escolheu **não criptografar at-rest no MVP** (mitigações: TLS, controle de acesso ao DB, mascaramento em log, audit log, backup criptografado).
4. Redigi `ADR-003-persistencia.md` em `status: proposed` cobrindo integralmente o mini-checklist Tipo 4:
   - ✅ Agregados identificados pela razão de negócio (Decisão 8 + seção "Agregados de domínio do MVP — modelo macro").
   - ✅ Diagrama ER Mermaid (`erDiagram`) com 7 entidades + cardinalidades.
   - ✅ Multi-tenancy: FK + trait `BelongsToUsuario` + Global Scope automático + Policy + 404-not-403.
   - ✅ Migrations: Laravel Migrations, reversíveis, idempotentes, expand → migrate → contract.
   - ✅ Audit log: tabela `audit_logs` append-only, app-side, correlacionada por `request_id` (ADR-002).
   - ✅ Extensões Postgres listadas: `citext` + `pgcrypto` adotadas; `pg_trgm`, `pgvector`, `PostGIS`, `TimescaleDB`, `pg_partman` rejeitadas com sinal de revisão.
   - ✅ Soft vs hard delete + LGPD: pipeline T+0 soft delete + T+30d job `AnonimizarUsuario`; audit log nunca anonimiza; hard delete só em rascunho 90d/failed_jobs/sessions.
5. Validei o diagrama Mermaid `erDiagram` com `@mermaid-js/mermaid-cli` localmente — primeira tentativa falhou no atributo `FK UK` (sintaxe não suportada); corrigi para `FK "UNIQUE"` (comentário) e o SVG de 239KB foi gerado limpo. Correção aplicada também no markdown da ADR — **CA-4 satisfeito**.
6. Atualizei `index.json` com entrada `ADR-003` em `decisions.adr[]` (status `proposed`, related_adrs/pdrs/epics/stories preenchidos) e `STORY-003` em `in_review` — **CA-7 satisfeito**.

### CA → evidência

- **CA-1** ✅ `defonline-docs/project-state/decisions/adr/ADR-003-persistencia.md` com `type: persistencia`.
- **CA-2** ✅ Mini-checklist Tipo 4 coberto integralmente.
- **CA-3** ✅ As três entidades canônicas (Usuario com CPF + email; EmpresaAnalisada com CNPJ/CPF; Diagnostico filho de Quiz que é filho de EmpresaAnalisada) modeladas + diagrama. 14 indicadores tratados como JSONB sob `Diagnostico.indicadores` (decisão de implementação fica para EPIC-002).
- **CA-4** ✅ Diagrama Mermaid `erDiagram` validado com `mmdc` (SVG 239KB, sem erro após correção).
- **CA-5** ✅ Direito de eliminação LGPD: Decisão 5 + pipeline T+0/T+30 detalhado; `deleted_at` (soft) + `anonimizado_at` + job `AnonimizarUsuario`.
- **CA-6** ✅ Pelo menos 2 alternativas reais nas decisões críticas: Decisão 1 (1A FK+Scope vs 1B RLS vs 1C ambos) e Decisão 6 (6A não criptografar vs 6B hash+crypt vs deferred). Demais decisões com alternativa registrada quando relevante.
- **CA-7** ✅ ADR `proposed`; `index.json` atualizado.

### Decisões deixadas explicitamente para outras spikes / IDRs

- Layout de pastas/namespaces, pacote concreto de audit log, particionamento de `audit_logs` → IDR do Programador.
- Storage físico do PDF, backup/DR formais → ADR de Infra (STORY-004).
- Ferramenta de monitoramento de `audit_logs` (alerta se taxa cair a zero) → ADR de Observabilidade (STORY-006).

### Próximos passos esperados

1. **PO revisa** ADR-003.
2. Se aceita: PO autoriza transição `proposed → accepted`; eu atualizo a ADR + `index.json` + movo estória para `done`.
3. EPIC-001 (Cadastro) e EPIC-002 (Diagnóstico) recebem schema concreto; STORY-007 pode rodar `php artisan migrate` no hello-world.

### Nenhum bloqueio. Nenhum [ESCALONAMENTO].

### Encerramento

- 2026-05-21 — PO Alexandro aprovou ADR-003 em chat. ADR transicionada para `accepted`; `index.json` atualizado; estória movida para `done`.
