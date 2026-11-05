---
story_id: STORY-081
slug: meu-plano-e-cancelamento
title: "Meu plano, troca de cartão e cancelamento"
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

# STORY-081 — Meu plano, troca de cartão e cancelamento

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

Os Termos prometem cancelamento a qualquer momento no painel e prestação de contas ao assinante.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.4 e §4.3
  - Spec de tela da STORY-070

## O quê

Dar ao usuário uma tela com plano, cota, créditos, cobranças e as ações de trocar cartão e cancelar.

## Por quê

Transparência reduz chargeback e reclamação, e permite recuperar uma assinatura em atraso.

## Critérios de aceite

- [ ] **CA-1:** A tela mostra plano, ciclo, próxima renovação e valor, cota restante e, por lote, créditos e vencimento, com alerta para lote perto de vencer.
- [ ] **CA-2:** A tela lista as cobranças com data, valor, desconto, status e meio de pagamento.
- [ ] **CA-3:** O usuário troca o cartão, e uma assinatura em atraso é cobrada novamente com o cartão novo.
- [ ] **CA-4:** O usuário cancela a renovação, a tela informa até quando o acesso vai, e não há nova cobrança. O acesso continua até o fim do ciclo pago.
- [ ] **CA-5:** O usuário reativa a renovação antes do fim do ciclo.
- [ ] **CA-6:** A tela informa como pedir arrependimento nos 7 dias corridos, quando aplicável.
- [ ] **CA-7:** Usuários sem plano e sem créditos veem a oferta de planos.
- [ ] **CA-8:** Durante o "Logar como", o admin vê a tela, mas não executa ações.
- [ ] **CA-9:** Teste E2E cobre cancelamento e troca de cartão.
- [ ] **CA-10:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Troca de plano (STORY-082).
- Reembolso (STORY-085).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-077
- **Bloqueia:** STORY-082, STORY-083, STORY-087

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
