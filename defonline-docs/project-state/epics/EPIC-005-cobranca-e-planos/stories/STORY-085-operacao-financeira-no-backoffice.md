---
story_id: STORY-085
slug: operacao-financeira-no-backoffice
title: "Backoffice — assinaturas, reembolso e nota fiscal"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-085 — Backoffice — assinaturas, reembolso e nota fiscal

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A equipe precisa atender pedidos de arrependimento e emitir nota fiscal, que os Termos prometem. A emissão fica fora do código no MVP (spec §2.4), mas precisa de controle.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.4
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.2
  - ADR-010

## O quê

Dar à equipe uma visão de assinaturas e cobranças, com reembolso e controle de nota fiscal.

## Por quê

Permite cumprir o arrependimento e a emissão de nota sem acesso direto ao banco.

## Critérios de aceite

- [ ] **CA-1:** Tela e ações exigem a permissão nova `financeiro.gerenciar`; sem ela, 404 e nenhum item no menu.
- [ ] **CA-2:** A tela lista e filtra assinaturas e cobranças por usuário, status e período.
- [ ] **CA-3:** A tela indica se uma cobrança está dentro dos 7 dias corridos e se houve análise desde a contratação, para orientar o arrependimento.
- [ ] **CA-4:** O admin solicita o reembolso de uma cobrança, com confirmação e motivo. Depois da confirmação do provedor, a cobrança fica "Estornada", e o acesso, a cota ou os créditos dela são retirados.
- [ ] **CA-5:** O mesmo reembolso não é feito duas vezes.
- [ ] **CA-6:** A tela lista cobranças pagas sem nota fiscal, e o admin as marca como emitidas.
- [ ] **CA-7:** Reembolso e marcação de nota vão para `audit_logs`.
- [ ] **CA-8:** Durante o "Logar como", essas ações ficam indisponíveis.

## Fora de escopo

- Emissão automática de nota fiscal.
- Inadimplência e dashboards do backoffice completo (roadmap §6.1).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-076, STORY-077
- **Bloqueia:** STORY-087

## Decisões já tomadas (não as reabra)

- PDR-020: catálogo em tela, trial, Asaas substituível, Termos `v2`, DRM em planos pagos e no trial, e cupons.

## Liberdade técnica

Você decide estrutura de código, tabelas, bibliotecas dentro dos ADRs e desenho dos testes. Não decide critérios de aceite nem padrões de qualidade. Se faltar uma decisão arquitetural, pare e registre em "Notas do agente".

## Definição de Pronto

- [ ] Todos os critérios de aceite têm teste e passam.
- [ ] `scripts/verificar.sh` verde.
- [ ] Commits em `main` local; push, PR e tag só com pedido do PO.
- [ ] IDR registrado se houve decisão técnica com impacto futuro.
- [ ] `index.json` e "Notas do agente" atualizados; status `in_review`.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao iniciar, atualize status e `owner_agent` aqui e no `index.json`. Se travar, use `status: blocked` e descreva o bloqueio.

## Notas do agente

### Decisões tomadas

### Descobertas

### Bloqueios encontrados

### IDRs criados

### Cobertura final

### Evidências
