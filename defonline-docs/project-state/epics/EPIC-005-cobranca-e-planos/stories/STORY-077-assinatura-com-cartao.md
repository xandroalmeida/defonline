---
story_id: STORY-077
slug: assinatura-com-cartao
title: "Assinatura de plano com cartão recorrente"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: true
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: L
---

# STORY-077 — Assinatura de plano com cartão recorrente

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A assinatura é o coração do modelo. O DEFOnline não pode armazenar dados de cartão (NRF §3.2).

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.1, §3.4 e §4.3
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.2 e §4
  - `defonline-docs/especificacao/V2/arquitetura-tecnica.md` §4.1
  - ADR-010
  - Spec de tela da STORY-070

## O quê

Permitir que o usuário assine um plano mensal ou anual com cartão e passe a ter cota e benefícios imediatamente.

## Por quê

Cria a receita recorrente que sustenta o produto.

## Critérios de aceite

- [ ] **CA-1:** O checkout mostra plano, ciclo, valor, próxima cobrança e renovação automática, e registra o aceite do Termo vigente.
- [ ] **CA-2:** Com o pagamento aprovado, a assinatura fica ativa e a cota está disponível de imediato.
- [ ] **CA-3:** Cartão recusado mantém o usuário sem assinatura e exibe o motivo em linguagem simples.
- [ ] **CA-4:** Dados de cartão não são gravados no banco nem aparecem em log. Só ficam token, bandeira e últimos 4 dígitos, conforme o ADR-010.
- [ ] **CA-5:** A assinatura guarda snapshot de preço e cota, e mudanças no catálogo não a afetam até a renovação.
- [ ] **CA-6:** A cota é renovada a cada mês no dia de aniversário, inclusive no ciclo anual, sem acumular, salvo configuração. Em um mês sem o dia de aniversário, vale o último dia do mês.
- [ ] **CA-7:** Um usuário com assinatura ativa não consegue contratar uma segunda.
- [ ] **CA-8:** O mesmo evento do provedor recebido duas vezes não ativa nem renova duas vezes.
- [ ] **CA-9:** Durante o "Logar como", o admin não consegue contratar.
- [ ] **CA-10:** Teste E2E percorre planos, checkout e assinatura ativa com o adaptador de teste.
- [ ] **CA-11:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Falha de renovação (STORY-079).
- Pix no anual (STORY-080).
- Cupom (STORY-083).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-069, STORY-070, STORY-073, STORY-075
- **Bloqueia:** STORY-078, STORY-079, STORY-080, STORY-081, STORY-083, STORY-085, STORY-086, STORY-087

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
