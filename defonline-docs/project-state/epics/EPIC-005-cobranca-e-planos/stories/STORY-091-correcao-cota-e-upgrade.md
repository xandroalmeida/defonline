---
story_id: STORY-091
slug: correcao-cota-e-upgrade
title: "Correção — cota do anual cancelado, cota contratada e upgrade proporcional"
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

# STORY-091 — Correção — cota do anual cancelado, cota contratada e upgrade proporcional

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-05, D-07, D-19. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [ ] **CA-1:** Assinatura anual cancelada, dentro do período pago, continua recebendo a cota mensal até o fim do ano pago, conforme a cláusula 8 do Termo (E25).
- [ ] **CA-2:** A cota contratada é retrato: mudança no catálogo não reduz a cota de quem já assina até ele trocar de plano (E16b). Isso se aplica a preço e cota, e não há reajuste automático.
- [ ] **CA-3:** **Decisão provisória do orquestrador, aguardando o PO (D-05):** no upgrade, a cota extra do ciclo em curso é proporcional aos dias restantes, com arredondamento para baixo e nunca menor que a cota já disponível no plano antigo. A cota cheia do plano novo vale da próxima renovação. A tela de revisão mostra as duas informações. Básico para Pro a 12 horas do fim não dá 9 análises extras (E6, E6b).
- [ ] **CA-4:** Repetir upgrade e downgrade não acumula cota nem reduz o preço pago (E6).

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente
