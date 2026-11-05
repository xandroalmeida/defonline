---
story_id: STORY-086
slug: eventos-e-alertas-de-cobranca
title: "Eventos e alertas de cobrança"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: S
---

# STORY-086 — Eventos e alertas de cobrança

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O épico não fecha sem medir conversão e detectar falhas de pagamento. O NRF §6 pede esses indicadores.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §6
  - `decisions/adr/ADR-004-observabilidade.md`
  - `product/north-star.md`

## O quê

Instrumentar o funil de contratação e alertar a equipe sobre falhas.

## Por quê

Sem isso, não saberemos se o preço funciona nem se a cobrança parou.

## Critérios de aceite

- [ ] **CA-1:** Há eventos de produto para: trial ativado e encerrado, checkout iniciado, contratação concluída ou abandonada, cupom aplicado, cancelamento e bloqueio por falta de saldo.
- [ ] **CA-2:** Os eventos passam pelo canal oficial e não trazem dado pessoal nem de cartão, conforme o teste arquitetural existente.
- [ ] **CA-3:** É possível calcular a conversão trial → pago em 30 dias, a conversão do checkout e o churn mensal.
- [ ] **CA-4:** A taxa de falha de pagamento acima de 10% em 1 hora gera alerta P2.
- [ ] **CA-5:** Falhas de autenticação do webhook e erros de processamento geram alerta.

## Fora de escopo

- Painel de métricas no backoffice.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-074, STORY-076, STORY-077, STORY-079
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
