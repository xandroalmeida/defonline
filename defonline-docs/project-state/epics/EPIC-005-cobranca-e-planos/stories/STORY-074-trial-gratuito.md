---
story_id: STORY-074
slug: trial-gratuito
title: "Trial gratuito"
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

# STORY-074 — Trial gratuito

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

O PDR-020 ativou o trial: 7 dias com 1 análise, valores configuráveis na STORY-071. Não exige meio de pagamento.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.3, §3.4 e §4.12 (notificações)
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §4.4

## O quê

Permitir que o usuário ative o trial uma única vez e usar o limite de dias e análises configurado.

## Por quê

Roberto vê o valor do produto antes de pagar. É o início do funil medido pelo épico.

## Critérios de aceite

- [ ] **CA-1:** Dado um Usuário elegível, quando ativa o trial, ele recebe os dias e as análises configurados, sem informar meio de pagamento.
- [ ] **CA-2:** O mesmo CPF não ativa o trial duas vezes. O mesmo CNPJ não recebe análise de trial em contas diferentes. A mensagem explica o motivo e oferece planos.
- [ ] **CA-3:** Mudanças na configuração do trial não alteram trials em andamento.
- [ ] **CA-4:** Com o trial desativado, a oferta não aparece e a ativação é recusada.
- [ ] **CA-5:** Ao fim do prazo, as funções pagas são bloqueadas sem nenhuma cobrança, e os diagnósticos seguem visíveis na janela de retenção.
- [ ] **CA-6:** O usuário recebe e-mail em D-2 e em D-0 com convite para assinar.
- [ ] **CA-7:** Mais trials do mesmo IP do que o limite configurável em 30 dias geram alerta à equipe, sem bloquear, porque escritórios compartilham IP.
- [ ] **CA-8:** Assinar durante o trial encerra o trial, sem somar a análise restante à cota.
- [ ] **CA-9:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Fora de escopo

- Bloqueio de novos trials por IP.
- Sequência de onboarding (roadmap §1.3).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-070, STORY-073
- **Bloqueia:** STORY-084, STORY-086, STORY-087

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
