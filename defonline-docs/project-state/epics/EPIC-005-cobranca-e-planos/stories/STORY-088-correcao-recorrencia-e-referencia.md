---
story_id: STORY-088
slug: correcao-recorrencia-e-referencia
title: "Correção — recorrência duplicada e cobrança na Invoice certa"
epic_id: EPIC-005
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: ready
owner_agent: null
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-088 — Correção — recorrência duplicada e cobrança na Invoice certa

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-01, D-02, D-03, D-16. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [ ] **CA-1:** Pagamento duplicado de assinatura (E1, E1b) cancela no provedor a recorrência excedente, registra a cobrança para devolução na lista da STORY-085, e o mês seguinte não volta a cobrar.
- [ ] **CA-2:** A correlação de cobrança com Invoice é pelo identificador da própria cobrança. Referência herdada da 1ª cobrança nunca faz a renovação paga ser ignorada, nem o estorno ou a contestação do mês 2 cair na Invoice do mês 1 (E2, E3, E3b). Vale nas duas hipóteses: o provedor repassa ou não `externalReference`.
- [ ] **CA-3:** Nenhum pagamento confirmado termina em `ignorado` sem Invoice e sem alerta. Se não houver destino, ele vai para a lista de devolução.
- [ ] **CA-4:** Os eventos `PAYMENT_CHARGEBACK_REVERSED`, `PAYMENT_RESTORED`, `PAYMENT_PARTIALLY_REFUNDED` e `PAYMENT_REFUND_DENIED`, conferidos na documentação oficial, têm tratamento: contestação revertida volta a paga; estorno negado volta a paga e alerta o admin; estorno parcial registra o valor, sem retirar o acesso.

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente
