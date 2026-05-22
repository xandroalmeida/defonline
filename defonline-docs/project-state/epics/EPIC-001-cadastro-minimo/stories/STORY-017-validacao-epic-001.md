---
story_id: STORY-017
slug: validacao-epic-001
title: Validação final do EPIC-001 Cadastro mínimo
epic_id: EPIC-001
sprint_id: null
type: validation
target_role: validador
status: draft
owner_agent: null
created_at: 2026-05-22
updated_at: 2026-05-22
estimated_session_size: M
---

# STORY-017 — Validação final do EPIC-001

> **Para o agente Validador:** carregue a skill `validador` e leia esta estória + o checklist por inteiro antes de começar. Você **não conserta** nada — apenas executa o checklist, registra evidências e produz o relatório. Em caso de falha, devolve para o PO sem tentar corrigir. Mesmo padrão da STORY-008 do EPIC-000.

## Contexto

Última estória do EPIC-001. O épico só pode ser marcado como `done` no `index.json` depois que esta validação produzir um `report.md` aprovado.

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md`
- Checklist: `epics/EPIC-001-cadastro-minimo/validation/checklist.md` — **a ser criado** pelo PO antes desta estória sair de `draft` para `ready`. Se ao iniciar você descobrir que ele não existe, pare e avise o PO no chat.
- Documentos canônicos do Validador:
  - `defonline-docs/skills/validador/SKILL.md`
  - `defonline-docs/skills/validador/references/validation-workflow.md`
  - `defonline-docs/skills/validador/references/evidence-discipline.md`
  - `defonline-docs/skills/validador/references/verdict-criteria.md`
  - `defonline-docs/skills/validador/references/reporting-craft.md`
  - `defonline-docs/skills/validador/templates/validation-report.md`
- Precedente útil para calibrar tom e profundidade: `epics/EPIC-000-foundation/validation/report.md` (validação do EPIC-000, dois passes — REJECTED não-bloqueante → APPROVED após STORY-010).

## O quê

Executar o checklist de validação do EPIC-001 em ordem, registrando para cada item: status (`pass | fail | n/a`), evidência (link, screenshot, log, comando reproduzível), e observações úteis. Produzir `epics/EPIC-001-cadastro-minimo/validation/report.md` usando o template do `validador`.

## Por quê

Sem validação independente, o EPIC-001 fecharia por confiança em quem implementou — anti-padrão. A validação garante que o fluxo cadastro-Usuário → confirmação-email → cadastro-Empresa → "Minhas Empresas" está real, observável, com gates de qualidade ativos, eventos de produto emitidos corretamente e sem PII vazando.

## Critérios de aceite

- [ ] **CA-1:** Cada item do `validation/checklist.md` do EPIC-001 foi exercido pelo Validador com evidência registrada.
- [ ] **CA-2:** `validation/report.md` produzido a partir do template, com veredito (`approved | approved_with_pending | rejected`) e justificativa por item.
- [ ] **CA-3:** Em caso de **rejected**, o relatório lista explicitamente quais CAs/itens falharam e sugere estórias de correção (sem implementá-las — apenas propor).
- [ ] **CA-4:** Em caso de **approved**, o `index.json` é atualizado para: EPIC-001 `status: done` + `validation_report` apontando para `validation/report.md` (com `verdict_history` se houver mais de um passe); STORY-017 `status: done`; STORY-011 a STORY-016 já em `done`.
- [ ] **CA-5:** Notas do agente preenchidas com tempo investido, dificuldades, observações úteis ao PO.

## Fora de escopo

- **Consertar** falhas — papel do Programador na estória seguinte.
- Validar épicos seguintes — escopo desta estória é apenas EPIC-001.
- Re-validar itens do EPIC-000 (esses já foram aprovados em 2026-05-22).

## Padrões de qualidade exigidos

Estória de validação — exceção declarada em `quality-standards.md`: Validador não escreve código de produção; produz relatório verificável. **Exigência mantida:** rigor de evidência (sem "passei no olho"; cada `pass` tem link/screenshot/log reproduzível).

## Dependências

- **Bloqueada por:** STORY-011 a STORY-016 todas em `in_review` ou `done`. **Promover esta estória para `ready` somente quando STORY-016 estiver `in_review`.**
- **Bloqueia:** abertura do EPIC-002 (próximo épico só começa após EPIC-001 `done`).
- **Pré-requisitos de ambiente:** acesso a homologação, ao repositório, ao pipeline, e ao `index.json`.

## Decisões já tomadas

- PDR-001 — escopo da WAVE-2026-01.
- ADRs 001 a 006 do EPIC-000 (continuam vigentes).
- IDRs 001 a 003 + qualquer IDR criado durante o EPIC-001.
- Checklist em `validation/checklist.md` — siga-o como está; mudança no checklist passa pelo PO.

## Liberdade do agente Validador

Você (Validador) decide:
- Como capturar evidência — screenshot, log, query SQL, comando reproduzível — desde que verificável.
- Ordem detalhada de execução dentro de cada bloco do checklist.
- Como redigir o relatório dentro do template.

Você (Validador) NÃO decide:
- Critérios de aprovação — vêm do checklist do PO.
- Mérito técnico das ADRs/IDRs — `n/a` se algo está fora do checklist.
- Estórias de correção em caso de falha — você **propõe**, o PO cria.

## Definição de Pronto

- [ ] Checklist executado integralmente com evidências.
- [ ] `report.md` produzido com veredito e justificativas.
- [ ] `index.json` atualizado (no caso de approved; em rejected, EPIC-001 fica `in_review`).
- [ ] Notas do agente preenchidas.
- [ ] `status: done` no frontmatter desta estória (só após o veredito ser emitido).

## Protocolo do agente (obrigatório)

Siga `agent-task-format.md`:

1. **Ao iniciar:** `status: in_progress`, `owner_agent: <id>`, `updated_at: <hoje>`. Atualize `index.json`.
2. **Durante:** TaskList interna por bloco do checklist; capture evidência em cada passo.
3. **Se travar** (ex.: não consegue acessar homologação): `status: blocked`, descreva em "Notas do agente".
4. **Ao terminar:** preencha "Notas do agente", emita veredito no `report.md`, atualize `index.json`, marque `status: done` aqui.

## Notas do agente

### Tempo investido
- <horas>

### Dificuldades encontradas
- <data> — <dificuldade>

### Observações úteis ao PO
- <data> — <observação>

### Veredito emitido
- <approved | rejected> em <data>. Relatório em `validation/report.md`.
