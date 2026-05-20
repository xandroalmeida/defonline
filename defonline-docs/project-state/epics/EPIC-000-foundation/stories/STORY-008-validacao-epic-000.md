---
story_id: STORY-008
slug: validacao-epic-000
title: Validação final do EPIC-000 Foundation
epic_id: EPIC-000
sprint_id: null
type: validation
target_role: validador
status: draft
owner_agent: null
created_at: 2026-05-20
updated_at: 2026-05-20
estimated_session_size: M
---

# STORY-008 — Validação final do EPIC-000

> **Para o agente Validador:** carregue a skill `validador` e leia esta estória + o checklist por inteiro antes de começar. Você **não conserta** nada — apenas executa o checklist, registra evidências e produz o relatório. Em caso de falha, devolve para o PO sem tentar corrigir.

## Contexto

Esta é a última estória do EPIC-000. O épico só pode ser marcado como `done` no `index.json` depois que esta validação produzir um `report.md` aprovado. O checklist a executar está em `epics/EPIC-000-foundation/validation/checklist.md`.

- Épico: `epics/EPIC-000-foundation/epic.md`
- Checklist: `epics/EPIC-000-foundation/validation/checklist.md`
- Documentos canônicos:
  - `defonline-docs/skills/validador/SKILL.md`
  - `defonline-docs/skills/validador/references/validation-workflow.md`
  - `defonline-docs/skills/validador/references/evidence-discipline.md`
  - `defonline-docs/skills/validador/references/verdict-criteria.md`
  - `defonline-docs/skills/validador/references/reporting-craft.md`
  - `defonline-docs/skills/validador/templates/validation-report.md`

## O quê

Executar o checklist de validação do EPIC-000 em ordem, registrando para cada item: status (`pass | fail | n/a`), evidência (link, screenshot, log, comando reproduzível), e qualquer observação útil. Produzir `epics/EPIC-000-foundation/validation/report.md` usando o template do `validador`.

## Por quê

Sem validação independente, o EPIC-000 fecharia por confiança em quem executou — anti-padrão. A validação garante que pipeline, homologação, ambiente local, ADRs e cobertura estão real e observavelmente em pé, não apenas declarados.

## Critérios de aceite

- [ ] **CA-1:** Cada item do `validation/checklist.md` foi exercido pelo validador com evidência registrada.
- [ ] **CA-2:** `validation/report.md` produzido a partir do template do `validador`, com veredito final (`APROVADO` ou `REPROVADO`) e justificativa por item de checklist.
- [ ] **CA-3:** Em caso de **REPROVADO**, o relatório lista explicitamente quais CAs/itens falharam e sugere estórias de correção (sem implementá-las — apenas propor).
- [ ] **CA-4:** Em caso de **APROVADO**, o `index.json` é atualizado para: épico EPIC-000 com `status: done` + `validation_report` apontando para `validation/report.md`; estória STORY-008 com `status: done`; estória STORY-007 com `status: done`.
- [ ] **CA-5:** Notas do agente preenchidas com tempo investido, dificuldades, observações úteis ao PO.

## Fora de escopo

- **Consertar** falhas encontradas — papel do Programador, não do Validador.
- Validar conteúdo das ADRs em mérito técnico (princípio "validador não decide arquitetura") — apenas confere que existem, estão `accepted` e indexadas.
- Validar épicos seguintes — escopo desta estória é EPIC-000.

## Padrões de qualidade exigidos

Estória de validação — exceção declarada conforme `quality-standards.md`: validador não escreve código de produção; produz relatório verificável. **Exigência mantida:** rigor de evidência (sem "passei no olho"; cada `pass` tem link/screenshot/log reproduzível).

## Dependências

- **Bloqueada por:** STORY-001 a STORY-007 (todas em `in_review` ou `done`).
- **Bloqueia:** abertura do EPIC-001 (próximo épico só começa após EPIC-000 `done`).
- **Pré-requisitos de ambiente:** acesso a homologação, ao repositório de código, ao pipeline de CI, e ao `index.json`.

## Decisões já tomadas

- PDR-001 — escopo da WAVE-2026-01.
- As 6 ADRs do EPIC-000 (aceitas pelo PO durante as STORY-001 a STORY-006).
- Checklist em `validation/checklist.md` — siga-o como está; mudança no checklist passa pelo PO.

## Liberdade do agente Validador

Você (Validador) decide:
- Como capturar evidência (screenshot, log, comando reproduzível) — desde que verificável.
- Ordem detalhada de execução dentro de cada seção do checklist.
- Como redigir o relatório dentro do template.

Você (Validador) NÃO decide:
- Critérios de aprovação — vêm do checklist do PO.
- Mérito técnico das ADRs — `n/a` se algo está fora do checklist.
- Estórias de correção em caso de falha — você **propõe**, mas o PO cria.

## Definição de Pronto

- [ ] Checklist executado integralmente com evidências.
- [ ] `report.md` produzido com veredito e justificativas.
- [ ] `index.json` atualizado (no caso de APROVADO; no caso de REPROVADO, EPIC-000 fica `in_review`).
- [ ] Notas do agente preenchidas.
- [ ] `status: done` no frontmatter desta estória (só após o veredito ser emitido).

## Protocolo do agente (obrigatório)

Siga `agent-task-format.md`. Em resumo:

1. **Ao iniciar:** `status: in_progress`, `owner_agent: <id>`, `updated_at: <hoje>`. Atualize `index.json`.
2. **Durante:** TaskList interna por seção do checklist; capture evidência em cada passo.
3. **Se travar** (ex.: não consegue acessar homologação): `status: blocked`, descreva o bloqueio em "Notas do agente".
4. **Ao terminar:** preencha "Notas do agente", emita veredito no `report.md`, atualize `index.json` conforme veredito. Estória vai para `done`.

## Notas do agente
(preenchido durante/após execução)

### Tempo investido
- <horas>

### Dificuldades encontradas
- <data> — <dificuldade>

### Observações úteis ao PO
- <data> — <observação>

### Veredito emitido
- APROVADO / REPROVADO em <data>. Relatório em `validation/report.md`.
