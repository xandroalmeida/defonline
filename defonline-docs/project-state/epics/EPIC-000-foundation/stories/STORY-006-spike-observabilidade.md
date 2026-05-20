---
story_id: STORY-006
slug: spike-observabilidade
title: SPIKE — Decisão de observabilidade + captura de eventos de produto
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

# STORY-006 — SPIKE de observabilidade

> **Para o agente Arquiteto:** spike → ADR `accepted`, sem código de produção. Esta ADR cobre duas faces fortemente correlatas: observabilidade técnica (logs/métricas/tracing) e captura de eventos de produto (analytics para o north star) — ambas vivem do mesmo "encanamento" de telemetria.

## Contexto

Sem observabilidade desde o dia 1, debug em produção vira arqueologia e métricas de produto ficam para depois — que é o mesmo que nunca. O north star do DEFOnline (`product/north-star.md`) exige captura de eventos de produto (`usuario_cadastrado`, `empresa_cadastrada`, `quiz_iniciado`, `diagnostico_concluido`, `diagnostico_visualizado`, `comparativo_aberto`) já no EPIC-001. Esta spike decide o trilho dessa captura, junto da observabilidade técnica básica.

A justificativa para concentrar observabilidade técnica e eventos de produto em uma única ADR (e não duas spikes separadas) é o critério "ADRs fortemente correlatas que decidem o mesmo subsistema e cuja decisão de uma trava a decisão da outra" do `story-craft.md` — ambas vivem do mesmo pipeline de telemetria, dos mesmos princípios de mascaramento de PII (cruza com `security-architecture.md`) e da mesma decisão de stack (princípio #3 do Arquiteto: Postgres-first se cabível).

- Épico: `epics/EPIC-000-foundation/epic.md`
- Documentos canônicos:
  - `defonline-docs/skills/arquiteto/SKILL.md` e `architecture-principles.md`
  - `defonline-docs/skills/arquiteto/references/adr-types.md` — Tipo 6 Observabilidade (mini-checklist)
  - `defonline-docs/skills/arquiteto/references/nfr-architecture.md` (sinais coletados)
  - `defonline-docs/skills/arquiteto/references/security-architecture.md` (mascaramento PII)
  - `defonline-docs/skills/arquiteto/templates/adr.md`
  - `defonline-docs/project-state/product/north-star.md` (eventos necessários listados)
  - `defonline-docs/skills/programador/references/observability-discipline.md`

## O quê

Produzir **ADR `proposed`** com `type: observabilidade` decidindo:

- **Observabilidade técnica:** stack de logs estruturados + métricas + tracing (preferência por solução simples e barata, Postgres-first se atender — princípio #3); formato de log padrão do projeto; health checks (liveness + readiness); métricas RED (rate, errors, duration) automáticas em endpoints; política de mascaramento de PII em log; alertas mínimos (serviço down, taxa de erro alta, latência ruim); retenção; estimativa de custo recorrente.
- **Eventos de produto (analytics):** modelo de captura dos eventos do north star (`usuario_cadastrado`, `empresa_cadastrada`, `quiz_iniciado`, `diagnostico_concluido`, `diagnostico_visualizado`, `comparativo_aberto`); destino (Postgres mesmo? Tabela `evento_produto` append-only? Ferramenta externa?); convenção de naming + propriedades; latência aceitável entre evento real e disponibilidade na query do PO; sem PII em payload.

## Por quê

Sem captura de eventos definida, north star não é mensurável a partir do primeiro diagnóstico em produção, o que mata o próprio aprendizado da onda. Sem observabilidade técnica, qualquer incidente em homologação vira "olhar no log do container manualmente" — viola princípio de automação.

## Critérios de aceite

- [ ] **CA-1:** ADR em `decisions/adr/ADR-XXX-observabilidade.md`, `type: observabilidade`.
- [ ] **CA-2:** ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 6: stack de observabilidade; formato de log estruturado; health checks; métricas RED; política de mascaramento de PII; alertas mínimos; estimativa de custo.
- [ ] **CA-3:** ADR define explicitamente, para os 6 eventos de produto listados em `north-star.md`: nome final do evento, propriedades obrigatórias, destino, latência aceitável para query. Convenção de naming consistente com o restante do projeto.
- [ ] **CA-4:** ADR explicita política de PII (nada de CPF, email, telefone, dados financeiros em log ou evento). Mascaramento automatizado.
- [ ] **CA-5:** Mínimo 2 alternativas reais nas decisões críticas (ex.: solução cloud-nativa do provedor vs solução self-hosted simples vs Postgres + tabela própria).
- [ ] **CA-6:** ADR submetida ao PO. `index.json` atualizado.

## Fora de escopo

- Dashboards de produto detalhados (sairão como decisão de implementação quando volume justificar).
- Ferramenta de A/B testing (não previsto no MVP).
- Análise preditiva por IA — roadmap §2.3 (v2.0).
- BI / data warehouse (não pertinente ao volume atual).

## Padrões de qualidade exigidos

Spike — mesma exceção declarada em STORY-001.

## Dependências

- **Bloqueada por:** nada (paralelizável com STORY-001 a STORY-005).
- **Bloqueia:** STORY-007 (hello world deveria já vir com health check + log básico). EPIC-001 (cadastro emite `usuario_cadastrado`, `empresa_cadastrada`).
- **Acoplamento leve com STORY-001 (Stack):** biblioteca de log e tracer pode vir do framework.

## Decisões já tomadas

- 6 eventos de produto canônicos listados em `product/north-star.md`.
- Princípio #3 (Postgres-first até prova que não atende).
- Princípio "qualidade é requisito" do PO (observabilidade não fica para depois).

## Liberdade técnica do agente Arquiteto

Você decide stack de observabilidade + modelo de eventos. Você **não decide** stack do app, topologia, persistência (mas pode referenciar PostgreSQL como destino), infra, CI/CD.

## Definição de Pronto

- [ ] CAs satisfeitos. ADR `proposed`. `index.json` atualizado. Notas preenchidas. `status: in_review`.

## Protocolo do agente

Siga `agent-task-format.md`. Igual à STORY-001.

## Notas do agente
(preenchido durante/após execução)
