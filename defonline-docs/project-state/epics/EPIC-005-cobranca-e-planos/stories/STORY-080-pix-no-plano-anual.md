---
story_id: STORY-080
slug: pix-no-plano-anual
title: "Pix no plano anual"
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

# STORY-080 — Pix no plano anual

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A spec permite Pix no ciclo anual. Pix não debita sozinho: a renovação depende de ação do usuário.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1 e §3.4
  - ADR-010

## O quê

Permitir assinar o plano anual com Pix e renovar por um novo QR Code.

## Por quê

Atende quem não quer ou não pode usar cartão, com a vantagem de receber o ano adiantado.

## Critérios de aceite

- [ ] **CA-1:** No plano anual, o checkout oferece cartão ou Pix; no mensal, só cartão.
- [ ] **CA-2:** A assinatura fica ativa após a confirmação do Pix, nunca antes.
- [ ] **CA-3:** Pix expirado não ativa a assinatura e pode ser gerado de novo.
- [ ] **CA-4:** Antes do fim do ciclo, o usuário recebe por e-mail o aviso e o caminho para pagar a renovação.
- [ ] **CA-5:** Sem pagamento até o fim do ciclo, as funções pagas são bloqueadas com o histórico preservado, sem retentativa de cobrança.
- [ ] **CA-6:** O mesmo webhook recebido duas vezes não ativa nem renova duas vezes.
- [ ] **CA-7:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Pix em plano mensal.

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
