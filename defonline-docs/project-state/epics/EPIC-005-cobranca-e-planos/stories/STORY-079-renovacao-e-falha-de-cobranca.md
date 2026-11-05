---
story_id: STORY-079
slug: renovacao-e-falha-de-cobranca
title: "Renovação e falha de cobrança"
epic_id: EPIC-005
sprint_id: null
type: implementation
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-079 — Renovação e falha de cobrança

> **Para o agente que vai executar:** leia esta estória por inteiro antes de começar. Se algo estiver ambíguo, registre a dúvida em "Notas do agente" e pause em vez de adivinhar.

## Contexto

A renovação não pode surpreender o usuário nem manter acesso sem pagamento. As regras já estão na spec e nos Termos.

- Épico: `epics/EPIC-005-cobranca-e-planos/epic.md`
- Ler antes:
  - `decisions/pdr/PDR-020-modelo-de-cobranca-planos-configuraveis-asaas.md`
  - `defonline-docs/especificacao/V2/especificacao-funcional.md` §2.4 e §3.7 (renovação)
  - `defonline-docs/especificacao/V2/requisitos-nao-funcionais-e-juridicos.md` §3.2 e tabela de contingência
  - ADR-010

## O quê

Renovar assinaturas, avisar antes do fim do ciclo e tratar falhas com retentativas.

## Por quê

Preserva receita e evita cancelamento involuntário.

## Critérios de aceite

- [ ] **CA-1:** O usuário recebe e-mail 7 dias antes do fim do ciclo, sem duplicar mesmo que a rotina rode de novo.
- [ ] **CA-2:** Na renovação paga, o ciclo avança com preço e cota vigentes naquele momento, e a mudança de preço só vale após o aviso de 30 dias.
- [ ] **CA-3:** Dada uma cobrança recusada, o usuário é avisado com link para atualizar o cartão, a assinatura fica "Em atraso" e o sistema tenta até 3 vezes, a cada 48 horas.
- [ ] **CA-4:** Pagamento durante as retentativas restabelece a assinatura e interrompe as tentativas.
- [ ] **CA-5:** Após a terceira falha, o ciclo é encerrado e as funções pagas são bloqueadas, com o histórico preservado.
- [ ] **CA-6:** Eventos fora de ordem ou perdidos não deixam a assinatura em estado errado, conforme a conciliação do ADR-010.
- [ ] **CA-7:** A indisponibilidade do provedor não é contada como recusa do cliente e gera alerta à equipe.
- [ ] **CA-8:** Respeite o interruptor de cobrança (STORY-073): enquanto estiver desligado, o usuário comum não vê nem sofre nenhum efeito desta estória.

## Ajustes do ADR-010 (prevalecem sobre os CAs acima onde houver conflito)

O ADR-010 está `proposed`. O orquestrador adotou as recomendações dele em 2026-11-05 para não parar o trabalho, e o PO ainda vai revisá-las.

- A retentativa de 3 vezes a cada 48 horas é uma régua própria nossa, a menos que o sandbox mostre retentativa do Asaas (ADR-010 §4.2). Registre o que foi observado. O mecanismo não pode gerar cobrança duplicada.
- A conciliação horária e diária do ADR-010 §3 entra aqui.

## Fora de escopo

- Tela de atualização do cartão (STORY-081).

## Padrões de qualidade exigidos

Siga `defonline-docs/skills/po/references/quality-standards.md`: TDD; cobertura de 80% no código novo e 98% em regras de negócio; teste E2E em browser quando houver fluxo de usuário; nada de setup manual.

## Dependências

- **Bloqueada por:** STORY-077
- **Bloqueia:** STORY-083, STORY-086, STORY-087

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
