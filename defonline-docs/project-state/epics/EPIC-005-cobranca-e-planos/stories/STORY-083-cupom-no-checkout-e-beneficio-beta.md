---
story_id: STORY-083
slug: cupom-no-checkout-e-beneficio-beta
title: "Cupom no checkout e benefício do beta"
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

# STORY-083 — Cupom no checkout e benefício do beta

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

Os usuários do beta recebem cupom (PDR-020). Com desconto total, eles não podem ser obrigados a informar meio de pagamento.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.5
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` §4.1
  - ADR-010

## O quê

Aplicar cupons digitados ou concedidos no checkout e tratar o fim do desconto.

## Por quê

Reconhece quem ajudou no beta e dá à equipe uma alavanca comercial.

## Critérios de aceite

- [ ] **CA-1:** O usuário digita um código e vê preço cheio, desconto e valor a pagar antes de confirmar.
- [ ] **CA-2:** Cupom concedido à conta é aplicado automaticamente, e o usuário vê essa informação.
- [ ] **CA-3:** Cupom vencido, inativo, esgotado, já usado pela conta ou que não cubra o item é recusado com o motivo. O uso é registrado só após a contratação ser concluída.
- [ ] **CA-4:** Com vários usuários no último uso disponível, o limite não é ultrapassado.
- [ ] **CA-5:** Cupons não acumulam. O valor final nunca fica negativo. Um valor final abaixo do mínimo do provedor segue a regra do ADR-010.
- [ ] **CA-6:** Com desconto total, a contratação não exige meio de pagamento nem chama cobrança no provedor, e a assinatura fica ativa.
- [ ] **CA-7:** O desconto vale pelo número de ciclos configurado, e a renovação usa o valor com desconto enquanto ele durar.
- [ ] **CA-8:** O usuário é avisado 7 dias antes do fim do desconto. Depois, a assinatura segue pelo preço vigente; sem meio de pagamento, as funções pagas são bloqueadas até a regularização, e o usuário pode informar o cartão em Meu plano.
- [ ] **CA-9:** Desativar o cupom não altera descontos já aplicados.
- [ ] **CA-10:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Vários cupons em uma compra.

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-072, STORY-076, STORY-077, STORY-079, STORY-081
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
