---
story_id: STORY-087
slug: validacao-final-cobranca
title: "Validação final do EPIC-005"
epic_id: EPIC-005
sprint_id: null
type: validation
target_role: validador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-087 — Validação final do EPIC-005

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

Última estória do épico. Precede o lançamento: ligar o interruptor é decisão do PO depois da aprovação.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `epics/EPIC-005-cobranca-e-planos/validation/checklist.md`

## O quê

Executar `validation/checklist.md` e produzir `validation/report.md`.

## Por quê

Reduz o risco de cobrar errado de um cliente real.

## Critérios de aceite

- [ ] **CA-1:** Todos os itens do checklist têm status e evidência.
- [ ] **CA-2:** Os fluxos de dinheiro foram percorridos no sandbox do Asaas: Pix, cartão, recusa, renovação, falha, cancelamento, reembolso e cupom de 100%.
- [ ] **CA-3:** Em produção, com o interruptor desligado, um usuário do beta não percebe mudança.
- [ ] **CA-4:** O relatório traz o veredito e a lista do que falta para o PO ligar a cobrança, incluindo chaves de produção e concessão do cupom do beta.

## Fora de escopo

- Corrigir falhas: elas voltam ao PO.
- Ligar a cobrança.

## Padrões de qualidade exigidos

Siga a skill `validador` e nunca faça escrita em dados reais de produção.

## Dependências

- **Bloqueada por:** STORY-069, STORY-070, STORY-071, STORY-072, STORY-073, STORY-074, STORY-075, STORY-076, STORY-077, STORY-078, STORY-079, STORY-080, STORY-081, STORY-082, STORY-083, STORY-084, STORY-085, STORY-086
- **Bloqueia:** nenhuma

## Decisões já tomadas (não as reabra)

- PDR-020: catálogo em tela, trial, Asaas substituível, Termos `v2`, DRM em planos pagos e no trial, e cupons.

## Liberdade técnica

Siga a skill `validador`: registre o que observou e não corrija.

## Definição de Pronto

- [ ] `validation/report.md` preenchido com veredito.
- [ ] `index.json` atualizado.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao iniciar, atualize status e `owner_agent` aqui e no `index.json`. Se travar, use `status: blocked` e descreva o bloqueio.

## Notas do agente

### Decisões tomadas

### Descobertas

### Bloqueios encontrados

### IDRs criados

### Cobertura final

### Evidências
