---
story_id: STORY-004
slug: spike-infra
title: SPIKE — Decisão de infra (cloud, IaC, 3 ambientes, Docker, custo)
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

# STORY-004 — SPIKE de infra

> **Para o agente Arquiteto:** spike → ADR `accepted`, sem código de produção. Aqui se decide onde tudo roda e como tudo se cria do zero.

## Contexto

O DEFOnline precisa nascer com três ambientes: dev local, homologação acessível por URL, e plano de produção (não obrigatório provisionar produção real nesta onda, mas a topologia precisa estar prevista). Princípios aplicáveis: entrega em produção desde dia 1 (PO), Docker local com paridade alta com produção (#6 e #8 do Arquiteto), IaC sempre (princípio de automação do PO), custo recorrente em ordem de magnitude (#11 do Arquiteto).

- Épico: `epics/EPIC-000-foundation/epic.md`
- Documentos canônicos:
  - `defonline-docs/skills/arquiteto/SKILL.md` e `architecture-principles.md`
  - `defonline-docs/skills/arquiteto/references/adr-types.md` — Tipo 5 Infra (mini-checklist)
  - `defonline-docs/skills/arquiteto/references/nfr-architecture.md` (NFRs de infra — uptime, latência)
  - `defonline-docs/skills/arquiteto/templates/adr.md`
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §1, §2, §3 (NFRs de hospedagem e segurança)

## O quê

Produzir **ADR `proposed`** com `type: infra` decidindo: provedor cloud + região + serviços principais; ferramenta de IaC (Terraform, Pulumi, ou justificativa para outra escolha); definição dos três ambientes (local, homologação, produção) — o que cada um oferece, o que difere, e por quê; estratégia de Docker para dev local (princípios #6 e #8); estimativa de custo recorrente mensal por ambiente; runbook automatizado para recriar do zero qualquer ambiente.

Para esta onda, **homologação precisa estar real e funcionando** (ela é parte do critério de pronto do EPIC-000); produção pode ser apenas "previsto na IaC, ainda não aplicado" se o custo justificar.

## Por quê

Sem decisão de infra, STORY-005 (CI/CD) não tem alvo de deploy. STORY-007 (hello world) não tem onde subir. Princípio "entrega em produção desde dia 1" do PO exige homologação acessível por URL ao fim do EPIC-000 — esta spike define como isso vai existir.

## Critérios de aceite

- [ ] **CA-1:** ADR em `decisions/adr/ADR-XXX-infra.md`, `type: infra`.
- [ ] **CA-2:** ADR cobre integralmente o mini-checklist do `adr-types.md` Tipo 5: provedor + região + serviços principais nomeados; IaC definida; três ambientes configurados desde dia 1; diferenças entre ambientes nomeadas e justificadas; estimativa de custo recorrente por ambiente; como recriar do zero; backup + restore com runbook automatizado.
- [ ] **CA-3:** ADR inclui diagrama de infra (Mermaid: componentes, rede, edge) — recomendado pelo `adr-types.md`.
- [ ] **CA-4:** Mínimo 2 alternativas reais de provedor cloud + status quo, com prós e contras objetivos (preferencialmente baseados em custo, lock-in, ergonomia de IaC, presença regional Brasil).
- [ ] **CA-5:** ADR explicita custo orientativo dos 3 ambientes (ordem de magnitude — não cotação exata) e o custo da homologação real que vai operar durante a WAVE-2026-01.
- [ ] **CA-6:** ADR explicita o domínio público que homologação vai usar (`homolog.defonline.com.br` ou outro decidido pelo Arquiteto).
- [ ] **CA-7:** ADR submetida ao PO. `index.json` atualizado.

## Fora de escopo

- Pipeline CI/CD detalhado — STORY-005 (mas pode acoplar leve com infra).
- Observabilidade — STORY-006.
- Decisão de backup/DR formal de produção — referência ao NFR é suficiente; aprofundamento entra quando produção real for ativada na onda 2.
- LGPD avançada (DPA, transferência internacional) — pode entrar como restrição na escolha do provedor, mas não como sub-spike própria.

## Padrões de qualidade exigidos

Spike — mesma exceção declarada em STORY-001.

## Dependências

- **Bloqueada por:** nada (paralelizável com STORY-001, 002, 003, 005, 006).
- **Bloqueia:** STORY-007 (precisa de homologação acessível).
- **Acoplamento leve com STORY-005 (CI/CD):** o provedor cloud pode influenciar a escolha de pipeline; sinalizar nas duas spikes em caso de tensão.

## Decisões já tomadas

- PDR-001 (escopo da onda).
- Princípio "entrega em produção desde dia 1" — homologação acessível por URL é mandatório.
- Princípio "automação por padrão" — IaC, não cliques manuais.

## Liberdade técnica do agente Arquiteto

Você decide cloud, IaC, ambientes, rede. Você **não decide** stack, topologia, persistência, CI/CD, observabilidade — cada um em sua spike.

## Definição de Pronto

- [ ] CAs satisfeitos. ADR `proposed`. Diagrama renderiza. `index.json` atualizado. Notas preenchidas. `status: in_review`.

## Protocolo do agente

Siga `agent-task-format.md`. Igual à STORY-001.

## Notas do agente
(preenchido durante/após execução)
