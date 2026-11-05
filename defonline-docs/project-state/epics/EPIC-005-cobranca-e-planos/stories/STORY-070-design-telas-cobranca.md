---
story_id: STORY-070
slug: design-telas-cobranca
title: "Design das telas de cobrança do usuário"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: designer
requires_design: true
status: in_review
owner_agent: "designer (claude-sonnet-5-5)"
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-070 — Design das telas de cobrança do usuário

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

Cobrança é o primeiro fluxo em que o usuário entrega dinheiro. Precisa de spec de tela antes do código. As telas de backoffice seguem o padrão já existente e ficam fora.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2, §3.4 e §4.3
  - `defonline-docs/especificacao/V2/design-system.md`

## O quê

Produzir as specs de tela de planos, checkout, Pix, meu plano, bloqueio por falta de análise e avisos de trial e cobrança.

## Por quê

Roberto precisa entender o que compra, quanto paga, quando renova e como cancelar, sem letra miúda.

## Critérios de aceite

- [ ] **CA-1:** Há spec `ready`, aprovada pelo PO, para: planos e preços; checkout com cupom e aceite do Termo; QR Code Pix com expiração; confirmação; meu plano; cancelamento; e bloqueio sem cota, créditos ou trial.
- [ ] **CA-2:** Cada spec cobre mobile e desktop, estados de carregamento, erro, sucesso e vazio, e segue o design system v1.
- [ ] **CA-3:** O checkout mostra preço cheio, desconto, valor a pagar, ciclo, próxima cobrança e valor das cobranças seguintes antes da confirmação.
- [ ] **CA-4:** O cancelamento é simples de encontrar, e a tela informa até quando o acesso continua.
- [ ] **CA-5:** O microtexto usa o nome "Raio X do Negócio" para o DRM.
- [ ] **CA-6:** As specs entram em `design.screens[]` no índice, com `implemented_by` apontando para as estórias de implementação.

## Fora de escopo

- Telas de backoffice (seguem o padrão existente).
- Hotsite de vendas (EPIC-009).

## Padrões de qualidade exigidos

Siga a skill `designer` e o design system v1, com acessibilidade conforme a STORY-059.

## Dependências

- **Bloqueada por:** nenhuma
- **Bloqueia:** STORY-074, STORY-075, STORY-076, STORY-077, STORY-087

## Decisões já tomadas (não as reabra)

- PDR-020: catálogo em tela, trial, Asaas substituível, Termos `v2`, DRM em planos pagos e no trial, e cupons.

## Liberdade técnica

Siga a skill `designer`. Regras comerciais vêm da spec e do PDR-020; lacunas viram pergunta ao PO.

## Definição de Pronto

- [ ] Specs aprovadas pelo PO em `design/screens/` e indexadas.
- [ ] `index.json` atualizado; status `in_review`.

## Protocolo do agente

Siga `defonline-docs/skills/po/references/agent-task-format.md`. Ao iniciar, atualize status e `owner_agent` aqui e no `index.json`. Se travar, use `status: blocked` e descreva o bloqueio.

## Notas do agente

### Decisões tomadas
- 10 specs `draft` em `design/screens/` (9 telas pedidas mais o bloqueio da STORY-073). A spec de `checkout-pix-creditos` é a base estrutural dos demais checkouts.
- 3 DDRs `proposed`: DDR-008 (resumo do pedido, seis linhas fixas), DDR-009 (seis situações do plano e avisos sem urgência; cancelar na primeira dobra) e DDR-010 (`seletor-modo` generalizado para meio de pagamento e ciclo).
- Componente novo: `resumo-pedido`. Zero token novo.
- CA-1: as specs estão `draft`; ficam `ready` só com aprovação do PO.

### Descobertas
- `confirmacao` (diálogo) existe em código mas não consta em `components.md`; registrar na aceitação do DDR-009.
- ADR-010 ainda não existe: campos de cartão, dados do titular e valor mínimo por cobrança ficaram como premissas declaradas nas specs.
- A STORY-073 precisa devolver o **motivo** do bloqueio, não só "sem saldo".

### Bloqueios encontrados
Nenhum.

### IDRs criados
Nenhum (DDR-008, 009 e 010, `proposed`).

### Cobertura final
CA-1 pendente de aprovação do PO; CA-2 a CA-5 atendidos nas specs; CA-6 depende do orquestrador (entradas em `design.screens[]` e `decisions.ddr[]` listadas no relatório).

### Evidências
`defonline-docs/project-state/design/screens/STORY-070-*.md` e `decisions/ddr/DDR-008..010`.
