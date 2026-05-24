---
story_id: STORY-017
slug: validacao-epic-001
title: Validação final do EPIC-001 Cadastro mínimo
epic_id: EPIC-001
sprint_id: null
type: validation
target_role: validador
status: done
owner_agent: validador (claude-opus-4-7)
created_at: 2026-05-22
updated_at: 2026-05-24
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
- ~1,5 hora de sessão única (leitura do checklist + epic + 7 estórias + 4 references + template; execução dos 47 itens; redação do `report.md`; atualização do índice).

### Dificuldades encontradas
- 2026-05-24 — **Acesso restrito ao Postgres de homologação** — itens de evidência direta no banco (queries em `term_acceptances`, `audit_logs`, `evento_produto`, `business_metrics`, `usuarios.senha_hash`) precisaram cair em evidência indireta (código + migrations + REVOKE/GRANT + relato das notas + CI verde). Marcado como ressalva explícita nos itens 1.6, 3.2, 4.8, 7.4 e seção "Limitações da validação" do relatório.
- 2026-05-24 — **Sem inbox real nem dashboard Resend** — itens 3.2 e 7.4 ficaram com evidência indireta forte (IDR-007 accepted + `mail_mailer: resend` no vars.yml + URL signed 60 min no código). Recomendação: PO complementa com print do dashboard Resend antes de fechar o épico.
- 2026-05-24 — **Divergência STORY-014 (403) vs ADR-003/NRF (404)** — registrada como observação não-bloqueante (item 4.6), conforme acordado no próprio checklist do PO.
- 2026-05-24 — **Divergência nominal `kind` (checklist) vs `tipo` (schema do banco) em `business_metrics`** — registrada como ressalva nominal (item 4.8). Conteúdo está correto; só o nome do campo difere.
- 2026-05-24 — **Pré-condição "todas em `done`" divergente entre validation-workflow.md (skill) e STORY-017** — STORY-017 autoriza `in_review`, workflow padrão exige `done`. Conflito absorvido na classificação do item 8.1 (PASS com ressalva, pendência operacional do PO).

### Observações úteis ao PO
- 2026-05-24 — **Veredito APPROVED com pendências.** Zero fails técnicos. Recomendo fechar o EPIC-001 após as transições operacionais listadas na seção "Recomendação ao PO" do relatório (transicionar STORY-013, STORY-014, STORY-015, STORY-016 e STORY-018 para `done`; mudar EPIC-001 para `done`; opcionalmente fazer smoke manual de 15 min cobrindo as 3 zonas de evidência indireta — Resend dashboard, inbox real, rate-limit no submit Livewire real).
- 2026-05-24 — Disciplina do time foi alta: smoke read-only após lição da rc.1 STORY-011, segregação `app/Domain` com gate ≥98%, abstração `RfbCnpjClient` antes do provedor real, validador de PII em `EventLogger` com lista de chaves proibidas + regex. Padrões a propagar para EPIC-002.
- 2026-05-24 — Débitos operacionais visíveis nas notas das estórias mas fora do escopo desta validação: rotacionar chave cnpja (queimada no chat — STORY-018), corrigir `bump-rc.yml` para disparar `release-homolog.yml` via PAT (STORY-018), verificar domínio `defonline.xandrix.com.br` específico no Resend para remover override `MAIL_FROM_ADDRESS`.

### Veredito emitido
- **approved_with_pending** em 2026-05-24. Relatório em `validation/report.md`. Pendências documentadas como ressalvas (não fails). Decisão final de fechamento do EPIC-001 é do PO após aceitar este relatório.
