---
story_id: STORY-082
slug: troca-de-plano
title: "Upgrade e downgrade de plano"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-082 — Upgrade e downgrade de plano

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

Os Termos definem upgrade imediato e proporcional e downgrade no ciclo seguinte. Como o catálogo é configurável, "superior" é definido pelo preço, não pelo nome do plano.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1

## O quê

Permitir trocar de plano e de ciclo, com a cobrança proporcional na subida.

## Por quê

Quem cresce sobe sem trocar de conta. É o caminho natural de Roberto para o Pro quando atende mais empresas.

## Critérios de aceite

- [ ] **CA-1:** Antes de confirmar um upgrade, o usuário vê o valor proporcional da diferença no ciclo vigente.
- [ ] **CA-2:** Com o pagamento aprovado, o novo plano e sua cota valem na hora. Upgrade recusado mantém o plano atual.
- [ ] **CA-3:** O cálculo proporcional é coberto por testes, incluindo primeiro e último dia do ciclo, e nunca gera valor negativo.
- [ ] **CA-4:** O downgrade fica agendado para o próximo ciclo, visível em Meu plano, e pode ser desfeito antes disso.
- [ ] **CA-5:** Só é possível escolher planos ativos. Um plano desativado continua valendo para quem já o tem até a troca ou o cancelamento.
- [ ] **CA-6:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Crédito em conta para downgrade.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-081
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
