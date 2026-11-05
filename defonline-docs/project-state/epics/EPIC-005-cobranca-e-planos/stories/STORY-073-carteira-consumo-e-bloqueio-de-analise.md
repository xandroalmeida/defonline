---
story_id: STORY-073
slug: carteira-consumo-e-bloqueio-de-analise
title: "Carteira, interruptor de cobrança e bloqueio de análise"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: L
---

# STORY-073 — Carteira, interruptor de cobrança e bloqueio de análise

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A cobrança será construída aos poucos e publicada na `main`, enquanto os usuários do beta seguem usando o produto. O interruptor protege esses usuários até o lançamento. A carteira concentra a regra de consumo.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1, §2.2 e §4.3
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` §4.1 (Subscription, CreditLot)
  - `app/app/Actions/CalcularDiagnostico.php`

## O quê

Criar a carteira do usuário e fazer cada análise consumir cota ou crédito, com bloqueio quando não há saldo, tudo atrás de um interruptor.

## Por quê

É o que transforma plano e crédito em direito de uso, sem cobrar ou bloquear ninguém antes da hora.

## Critérios de aceite

- [ ] **CA-1:** Existe um interruptor de cobrança, desligado por padrão, alterável na tela da STORY-071 e auditado. Desligado, o fluxo de diagnóstico funciona como hoje e nada é consumido.
- [ ] **CA-2:** Ligado, ao concluir uma análise o sistema debita 1 da cota; sem cota, 1 crédito do lote com vencimento mais próximo.
- [ ] **CA-3:** Dado que não há cota nem créditos válidos, quando o usuário tenta iniciar ou concluir a análise, ela é bloqueada com orientação para assinar ou comprar, sem perder o rascunho do quiz.
- [ ] **CA-4:** Créditos vencidos não são consumidos, e lotes esgotados ficam marcados.
- [ ] **CA-5:** Falha no cálculo do diagnóstico não consome saldo.
- [ ] **CA-6:** Duas conclusões simultâneas com saldo 1 geram uma única análise concluída, e o saldo nunca fica negativo.
- [ ] **CA-7:** A carteira é do Usuário: análises em empresas diferentes consomem o mesmo saldo.
- [ ] **CA-8:** Cada consumo fica registrado com análise, Empresa Analisada e origem (cota ou crédito).
- [ ] **CA-9:** Diagnósticos já emitidos continuam acessíveis com ou sem saldo, dentro da janela de retenção.
- [ ] **CA-10:** Cobertura de 98% nas regras de consumo, com testes de concorrência.

## Fora de escopo

- Pagamento e renovação da cota (STORY-076 e STORY-077).
- Bloqueio do DRM (STORY-084).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-071
- **Bloqueia:** STORY-074, STORY-076, STORY-077, STORY-084, STORY-087

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
