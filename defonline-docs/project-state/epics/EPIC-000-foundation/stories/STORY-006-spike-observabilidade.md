---
story_id: STORY-006
slug: spike-observabilidade
title: SPIKE — Decisão de observabilidade + captura de eventos de produto
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

### Decisões tomadas

- **2026-05-21** — Trilho de observabilidade técnica: **Postgres-first puro** (logs JSON via Laravel Monolog em canal `stack` com `stdout` + `daily` 90d; métricas em tabelas `request_metrics`, `job_metrics`, `business_metrics`; fila/conexões consultadas ao vivo em `jobs` e `pg_stat_activity`). Decisor sob F1 (Postgres-first + custo R$0) e F6 (operável por time pequeno). Confirmado pelo PO via `AskUserQuestion`. Opções B (self-hosted lite) e C (cloud SaaS free tier) rejeitadas com sinais de revisão explícitos no ADR.
- **2026-05-21** — Destino dos 6 eventos do north star: **tabela `evento_produto` própria** no Postgres, append-only, schema dedicado (independente de `audit_logs`). `EventLogger::emit(...)` síncrono inline na transação do agregado. Latência alvo < 1s entre ato e disponibilidade na query do PO. Confirmado pelo PO via `AskUserQuestion`.
- **2026-05-21** — Canal de alerta: **Telegram** (bot privado). Em dev/local, canal vira `log` (sem internet). 7 alertas mínimos definidos (5 do RNF §6.4 + worker travado + scheduler down). Cooldown de 30 min por tipo. Confirmado pelo PO via `AskUserQuestion`.
- **2026-05-21** — Schema concreto dos 6 eventos do north star fixado (nome, quando emitir, `usuario_id`/`empresa_id` obrigatoriedade, propriedades obrigatórias) — ver Decisão 2 da ADR-004.
- **2026-05-21** — Política de PII em três camadas: `LogSanitizer` reaproveitado de ADR-003 para logs; `EventLogger::emit()` lança exceção se chave proibida; teste arquitetural Pest bloqueia merge. Princípio #9 (automatizável > documentável).
- **2026-05-21** — `request_id` UUID v7 da ADR-002 é a chave de correlação cruzando `request_metrics`, `job_metrics`, `business_metrics`, `evento_produto`, `audit_logs` e log estruturado. Esta ADR **consome** a convenção; não cria nova.
- **2026-05-21** — Health checks: `/health` (liveness, < 100ms, sem DB) + `/ready` (readiness com `SELECT 1` + checks de cache/queue). Heartbeat de worker e scheduler em tabelas próprias.
- **2026-05-21** — Tracing distribuído: **fora do MVP**. `request_id` cobre o monolito. Sinais de revisão explícitos: serviço externo síncrono crítico OU > 100k requests/dia sustentado.

### Descobertas

- **2026-05-21** — O RNF §6.4 lista 5 alertas mas **não nomeia canal** (item explicitamente `[A DEFINIR]` na §3.4 da especificação). A escolha de Telegram preenche essa lacuna; e-mail tinha latência alta (minutos) e webhook genérico era um nó extra agora sem destino concreto.
- **2026-05-21** — `pg_partman` aparece em ADR-003 como extensão **não no MVP** (`audit_logs` cabe em tabela única). Esta ADR herda a mesma postura para `request_metrics`/`evento_produto` — particionamento vira IDR do Programador quando volume ultrapassar ~50M linhas.
- **2026-05-21** — Volume estimado no MVP (~100 empresas ativas × 4 sessões/mês × 50 reqs/sessão ≈ 20k req/mês) é **3 ordens de grandeza** abaixo do que justifica Loki/Prometheus dedicado. Postgres aguenta com folga, justificando F1 (Postgres-first) por número, não por preferência.
- **2026-05-21** — `LogSanitizer` da ADR-003 cobre exatamente o que o RNF §6.1 exige (PII proibida em log); esta ADR só **estende a lista de chaves** redigidas (adiciona `cnae_principal` parcial, `nome_completo`, dados financeiros do quiz por regex). Não cria mecanismo paralelo.
- **2026-05-21** — `diagnostico_visualizado` é o único dos 6 eventos do north star que **exige idempotência de aplicação** (`(usuario_id, diagnostico_id, dia, via)`), porque GET é trivialmente repetível e poderia inflar a contagem. Documentado no schema 2.2.

### Bloqueios encontrados

- Nenhum bloqueio que exigisse escalonamento. Decisões 1–3 (trilho técnico, destino de eventos, canal de alerta) foram confirmadas pelo PO via `AskUserQuestion` antes da redação da ADR — princípio do SKILL.md ("ofereça as opções primeiro, antes de escrever o ADR").

### ADR produzida

- **ADR-004 — Observabilidade do DEFOnline — logs JSON via Laravel + métricas e eventos no Postgres + alertas via Telegram** — `decisions/adr/ADR-004-observabilidade.md` (status: `proposed`).

### Links de evidência

- ADR cobre integralmente o mini-checklist Tipo 6 de `adr-types.md` (CA-2): stack de observabilidade, formato de log estruturado, health checks (`/health` + `/ready`), métricas RED automáticas via middleware, política PII automatizada, 7 alertas mínimos (5 do RNF + 2 da topologia), estimativa de custo (~R$0 delta).
- ADR define explicitamente, para os 6 eventos do north star (CA-3), o nome final, propriedades obrigatórias, destino (`evento_produto`), latência aceitável (< 1s) — ver Decisão 2.
- ADR explicita política de PII em três camadas (CA-4): helper com exceção, teste arquitetural Pest, opcionalmente trigger Postgres. Mascaramento via `LogSanitizer` único.
- Alternativas reais (CA-5): A (Postgres-first), B (self-hosted lite Loki/Sentry), C (cloud SaaS free), D (status quo) — todas com prós/contras e veredito.
- `index.json` atualizado com entrada `ADR-004` em `decisions.adr[]` (CA-6).

### Aprovação humana

- **2026-05-21** — ADR-004 **aceita pelo PO Alexandro em chat**. Status `proposed` → `accepted`. Estória fechada como `done`. Sem condicionantes.
