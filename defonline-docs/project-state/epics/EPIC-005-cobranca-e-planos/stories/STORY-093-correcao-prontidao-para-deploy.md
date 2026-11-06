---
story_id: STORY-093
slug: correcao-prontidao-para-deploy
title: "Correção — prontidão para deploy"
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

# STORY-093 — Correção — prontidão para deploy

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-10, D-13, D-14, D-17, D-18, D-21. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [ ] **CA-1:** Sem provedor configurado e com a cobrança desligada, os agendadores saem com sucesso sem agir, e o webhook responde sem erro 500, em `APP_ENV=production` (E22). Com a cobrança ligada e sem provedor válido, o sistema recusa ligar o interruptor e explica o motivo.
- [ ] **CA-2:** O filtro de data do Financeiro usa `DataExibicao`, e `FinanceiroTest` passa em qualquer horário, com teste de relógio congelado às 22:30.
- [ ] **CA-3:** `database-schema.md` regenerado pela skill `analisar-banco` local (só local), frontmatter das estórias coerente com o índice, `.env.example` completo, e `ZALKVPS.md` ou README descrevem as variáveis e o webhook de cobrança.
- [ ] **CA-4:** Tentativas de cupom têm limite por conta e por IP (D-17).
- [ ] **CA-5:** O payload guardado do provedor remove `creditCardHolderInfo` e qualquer dado do titular (E14).
- [ ] **CA-6:** Chamadas de rede ao provedor não ocorrem dentro de transação com linha travada; se o provedor falhar, o estado local fica consistente e é reconciliado.

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente
