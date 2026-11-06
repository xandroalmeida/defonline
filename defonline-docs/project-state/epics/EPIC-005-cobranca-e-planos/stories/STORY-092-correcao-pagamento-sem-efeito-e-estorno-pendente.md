---
story_id: STORY-092
slug: correcao-pagamento-sem-efeito-e-estorno-pendente
title: "Correção — pagamento sem efeito, estorno pendente e acesso em atraso"
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

# STORY-092 — Correção — pagamento sem efeito, estorno pendente e acesso em atraso

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-08, D-11a, D-12. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [ ] **CA-1:** Todo pagamento confirmado sem efeito aparece na lista de devolução e gera alerta: diferença de upgrade paga depois da renovação (E4), duas diferenças pagas (E4b), checkout pago depois de cancelar (E21), segundo QR Code pago (E13). Cancelada ou não, uma assinatura não nasce sobre período vigente: E21 e E13 não concedem período a mais sem que o admin veja isso.
- [ ] **CA-2:** Abrir um novo checkout do mesmo item cancela no provedor o checkout pendente anterior, quando o provedor permitir.
- [ ] **CA-3:** Crédito com estorno em `aguardando_autorizacao` fica indisponível para consumo. Se o estorno for negado, o crédito volta (E12).
- [ ] **CA-4:** Assinatura em atraso mantém análises e Raio X do Negócio durante as retentativas, como dizem a spec §3.7 e o Termo. O bloqueio só ocorre ao encerrar o ciclo (E17). A causa `EmAtraso` passa a ser aviso, sem bloqueio, e a régua segue igual.

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente
