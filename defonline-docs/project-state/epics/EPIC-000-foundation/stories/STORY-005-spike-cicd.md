---
story_id: STORY-005
slug: spike-cicd
title: SPIKE — Decisão de CI/CD (pipeline, branching, deploy, rollback)
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

# STORY-005 — SPIKE de CI/CD

> **Para o agente Arquiteto:** spike → ADR `accepted`, sem código de produção.

## Contexto

O critério de pronto do EPIC-000 é "merge na main faz deploy automático em homologação". Esta spike decide como esse "faz deploy automático" acontece: ferramenta de CI/CD, modelo de branching, gates de qualidade, política de rollback, feature flags (se aplicáveis). Princípios aplicáveis: automação por padrão (PO), simplicidade de branching para time pequeno (princípio do Tipo 7 do `adr-types.md`).

- Épico: `epics/EPIC-000-foundation/epic.md`
- Documentos canônicos:
  - `defonline-docs/skills/arquiteto/SKILL.md` e `architecture-principles.md`
  - `defonline-docs/skills/arquiteto/references/adr-types.md` — Tipo 7 Política de evolução (mini-checklist)
  - `defonline-docs/skills/arquiteto/templates/adr.md`
  - `defonline-docs/skills/po/references/quality-standards.md` (cobertura mínima a ser gateada pelo pipeline)

## O quê

Produzir **ADR `proposed`** com `type: politica-evolucao` decidindo: modelo de branching (trunk-based vs GitFlow leve vs outro); ferramenta de CI/CD (GitHub Actions, GitLab CI, CircleCI, etc.); pipeline mínimo (lint + test + build + deploy); gates de cobertura conforme `quality-standards.md`; estratégia de deploy para homologação (automático a cada merge na main); estratégia de deploy para produção (automático ou gated, a decidir); estratégia de rollback (revert + redeploy, blue-green, ou outra); política de feature flags (ferramenta + padrão de remoção).

## Por quê

Sem CI/CD decidida, STORY-007 (hello world deploy) não tem trilho. Princípio "automação por padrão" do PO exige pipeline desde o dia 1. Cobertura sem gateamento vira "depois eu vejo" — falha cultural conhecida.

## Critérios de aceite

- [ ] **CA-1:** ADR em `decisions/adr/ADR-XXX-cicd.md`, `type: politica-evolucao`.
- [ ] **CA-2:** ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 7: modelo de branching simples; deploy automático para homologação a cada merge; deploy para produção definido; estratégia de rollback documentada; feature flags com ferramenta e política de remoção.
- [ ] **CA-3:** ADR explicita os gates de cobertura no pipeline (≥80% geral, ≥98% núcleo/regras de negócio) conforme `quality-standards.md` — pipeline vermelho se cobertura cair abaixo.
- [ ] **CA-4:** Mínimo 2 alternativas reais de ferramenta CI/CD + status quo (ex.: GitHub Actions vs GitLab CI vs solução do provedor cloud).
- [ ] **CA-5:** ADR inclui pseudocódigo ou diagrama Mermaid do pipeline (etapas: lint → test → build → deploy → smoke test em homolog).
- [ ] **CA-6:** ADR submetida ao PO. `index.json` atualizado.

## Fora de escopo

- Detalhes de provedor cloud onde rodam workers de CI — pode ter coupling com STORY-004 (Infra), sinalizar.
- Implementação real do `.yml` do pipeline — fica em STORY-007 (implementação) ou em IDR.
- Observabilidade do pipeline (que sinais coletar de duração, falha) — pode mencionar mas STORY-006 trata em detalhe.

## Padrões de qualidade exigidos

Spike — mesma exceção declarada em STORY-001.

## Dependências

- **Bloqueada por:** nada (paralelizável com STORY-001, 002, 003, 004, 006).
- **Bloqueia:** STORY-007.
- **Acoplamento leve com STORY-004 (Infra):** alvo de deploy vem da Infra.

## Decisões já tomadas

- Princípio "automação por padrão" — cliques manuais inaceitáveis.
- Princípio "qualidade é requisito" — cobertura gateada no pipeline.

## Liberdade técnica do agente Arquiteto

Você decide branching, ferramenta CI/CD, pipeline, rollback, flags. Você **não decide** stack, topologia, persistência, infra de hospedagem, observabilidade — cada um em sua spike.

## Definição de Pronto

- [ ] CAs satisfeitos. ADR `proposed`. `index.json` atualizado. Notas preenchidas. `status: in_review`.

## Protocolo do agente

Siga `agent-task-format.md`. Igual à STORY-001.

## Notas do agente
(preenchido durante/após execução)
