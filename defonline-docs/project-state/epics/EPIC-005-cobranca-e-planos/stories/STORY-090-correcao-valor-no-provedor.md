---
story_id: STORY-090
slug: correcao-valor-no-provedor
title: "Correção — valor da recorrência após troca de plano, cartão e cupom"
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

# STORY-090 — Correção — valor da recorrência após troca de plano, cartão e cupom

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-06, D-15, D-20. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [ ] **CA-1:** A troca de cartão usa o valor e o ciclo vigentes da assinatura no momento em que o checkout novo é confirmado. Upgrade feito depois de abrir a troca não deixa o preço antigo: o valor é corrigido no provedor, com repetição se ele falhar (E5).
- [ ] **CA-2:** A renovação com valor diferente do esperado não passa só com `warning`: gera alerta da equipe, e a divergência aparece na tela Financeiro.
- [ ] **CA-3:** Plano gratuito por cupom que recebe upgrade pago: a tela promete o que vai acontecer, e o usuário consegue cadastrar cartão antes do fim do desconto. A assinatura não expira sem saída (E23).
- [ ] **CA-4:** Downgrade agendado é aplicado também em renovação de ciclo gratuito (E24).

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente
