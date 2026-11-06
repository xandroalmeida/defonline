---
story_id: STORY-089
slug: correcao-datas-do-provedor
title: "Correção — data do provedor sem deslocar o ciclo"
epic_id: EPIC-005
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: S
---

# STORY-089 — Correção — data do provedor sem deslocar o ciclo

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-04. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [ ] **CA-1:** Data só com o dia, vinda do provedor, é interpretada no fuso de negócio (`DataExibicao`), nunca como meia-noite UTC. Pagamento em 05/11 às 15:00 de Brasília vence em 05/12 e dá acesso durante todo o dia 05/12 até a cobrança (E7).
- [ ] **CA-2:** O fim do acesso considera o dia do vencimento inteiro em Brasília, em assinatura com cartão, Pix anual e trial, e as telas mostram a mesma data que a regra usa.
- [ ] **CA-3:** Dados já gravados não são reinterpretados de forma errada; não há migração de dados em produção porque a cobrança ainda não foi ligada.

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente
