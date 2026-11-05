---
story_id: STORY-075
slug: pagina-de-planos
title: "Página de planos e preços"
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

# STORY-075 — Página de planos e preços

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O usuário precisa de um ponto de partida para contratar. O hotsite completo é do EPIC-009, mas os Termos remetem aos preços exibidos.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §4.10 (hotsite)
  - Spec de tela da STORY-070

## O quê

Exibir planos, pacotes e trial lidos do catálogo, com entrada para o checkout.

## Por quê

Roberto compara as opções em um só lugar.

## Critérios de aceite

- [ ] **CA-1:** A página lista planos ativos com cota, preço mensal e anual e os benefícios, incluindo o Raio X do Negócio.
- [ ] **CA-2:** A página lista pacotes ativos com quantidade, preço, unitário e validade, e deixa claro que eles não incluem o Raio X do Negócio.
- [ ] **CA-3:** A oferta de trial só aparece com o trial ativo e, para usuário logado, só se ele for elegível.
- [ ] **CA-4:** Uma alteração no catálogo aparece na página sem deploy.
- [ ] **CA-5:** Visitante é levado ao cadastro; usuário logado segue para o checkout do item escolhido.
- [ ] **CA-6:** Há acesso à página no menu e na mensagem de bloqueio da STORY-073.
- [ ] **CA-7:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Conteúdo de vendas do hotsite (EPIC-009).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-070, STORY-071
- **Bloqueia:** STORY-076, STORY-077, STORY-087

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
