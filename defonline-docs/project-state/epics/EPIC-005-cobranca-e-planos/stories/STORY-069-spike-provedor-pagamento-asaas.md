---
story_id: STORY-069
slug: spike-provedor-pagamento-asaas
title: "Spike — abstração do provedor de pagamento e integração Asaas"
epic_id: EPIC-005
sprint_id: null
type: spike
target_role: arquiteto
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-069 — Spike — abstração do provedor de pagamento e integração Asaas

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O PDR-020 escolheu o Asaas e exige que trocar de provedor não altere as regras de negócio. A cobrança não tem ADR. O padrão `RfbCnpjClient` (IDR-004) é uma referência de abstração de provedor no projeto.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.2
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` §3 e §4.1
  - `decisions/idr/IDR-004-rfb-provider-abstraction-cnpja-receitaws.md`

## O quê

Produzir o ADR-010, que define a abstração do provedor e como o Asaas atende aos requisitos de pagamento.

## Por quê

Sem esta decisão, cartão, Pix e webhook seriam construídos sobre suposições, e um erro aqui afeta dinheiro de cliente.

## Critérios de aceite

- [ ] **CA-1:** ADR-010 aceito define o contrato da abstração: cliente, cobrança avulsa, assinatura, cancelamento, estorno e eventos recebidos do provedor, sem tipos do Asaas fora do adaptador.
- [ ] **CA-2:** O ADR responde, com base na documentação e no sandbox do Asaas: como o cartão chega ao provedor sem que nossos servidores o armazenem ou registrem em log, e qual o impacto em PCI.
- [ ] **CA-3:** O ADR define autenticação do webhook, idempotência, reprocessamento de eventos fora de ordem e conciliação quando um webhook se perde.
- [ ] **CA-4:** O ADR confirma no sandbox: recorrência em cartão, Pix com expiração de 30 minutos, Pix em plano anual, valor mínimo por cobrança, estorno e cobrança com desconto. Cada lacuna traz uma alternativa e vira pergunta ao PO.
- [ ] **CA-5:** O ADR define os ambientes (sandbox fora de produção), a guarda das chaves via `scripts/segredos.sh` e um adaptador de teste para a suíte rodar sem rede.
- [ ] **CA-6:** O ADR ajusta o modelo conceitual de `arquitetura-tecnica.md` §4.1 no que for preciso, incluindo o identificador do provedor em cada registro externo.
- [ ] **CA-7:** O teste de troca está descrito: o que precisaria mudar para adicionar um segundo provedor.

## Fora de escopo

- Implementar a integração (STORY-076 e STORY-077).
- Reavaliar a escolha do Asaas, já decidida.

## Padrões de qualidade exigidos

Siga a skill `arquiteto`. Uma afirmação sobre o Asaas precisa de fonte na documentação oficial ou de teste no sandbox. Requer conta de sandbox fornecida pelo PO.

## Dependências

- **Bloqueada por:** nenhuma
- **Bloqueia:** STORY-076, STORY-077, STORY-087

## Decisões já tomadas (não as reabra)

- PDR-020: catálogo em tela, trial, Asaas substituível, Termos `v2`, DRM em planos pagos e no trial, e cupons.

## Liberdade técnica

Siga a skill `arquiteto`. Decisões de produto são do PDR-020; lacunas viram pergunta ao PO. Não implemente a integração.

## Definição de Pronto

- [ ] ADR-010 em `decisions/adr/` com status para aprovação e indexado.
- [ ] Perguntas ao PO listadas em "Notas do agente".
- [ ] `index.json` atualizado; status `in_review`.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao iniciar, atualize status e `owner_agent` aqui e no `index.json`. Se travar, use `status: blocked` e descreva o bloqueio.

## Notas do agente

### Decisões tomadas

### Descobertas

### Bloqueios encontrados

### IDRs criados

### Cobertura final

### Evidências
