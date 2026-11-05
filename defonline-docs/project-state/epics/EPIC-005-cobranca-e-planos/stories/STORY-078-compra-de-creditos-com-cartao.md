---
story_id: STORY-078
slug: compra-de-creditos-com-cartao
title: "Compra de créditos com cartão"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: S
---

# STORY-078 — Compra de créditos com cartão

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A spec prevê créditos à vista com Pix ou cartão. As duas partes já existem nas STORY-076 e STORY-077.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.2 e §4.3
  - ADR-010

## O quê

Permitir pagar um pacote com cartão, sem criar recorrência.

## Por quê

Dá uma alternativa a quem prefere cartão.

## Critérios de aceite

- [ ] **CA-1:** No checkout de pacote, o usuário escolhe Pix ou cartão.
- [ ] **CA-2:** Com o pagamento aprovado, os créditos entram na carteira e nenhuma recorrência é criada.
- [ ] **CA-3:** Cartão recusado não gera crédito e explica o motivo.
- [ ] **CA-4:** O mesmo evento recebido duas vezes credita uma única vez.
- [ ] **CA-5:** Dados de cartão seguem a regra da STORY-077.
- [ ] **CA-6:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- O cartão é digitado no Checkout hospedado (`DETACHED`, `CREDIT_CARD`).

## Fora de escopo

- Guardar cartão para compras futuras.

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
