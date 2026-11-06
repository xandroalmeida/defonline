---
story_id: STORY-094
slug: correcao-termos-e-arrependimento
title: "Correção — Termos v2 alinhados ao sistema"
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

# STORY-094 — Correção — Termos v2 alinhados ao sistema

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-11. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [ ] **CA-1:** Termo v2 (ainda não publicado, então sem nova versão): cl. 7 diz que as funções pagas continuam durante as retentativas e que o ciclo é encerrado se a cobrança não for paga em até 48 horas depois da terceira falha.
- [ ] **CA-2:** Cl. 8: assinatura em atraso pode ser cancelada a qualquer momento, com encerramento imediato do acesso, porque o ciclo não foi pago. O histórico e os lançamentos do Raio X do Negócio ficam disponíveis, sem prometer limite de 12 meses que o sistema não aplica.
- [ ] **CA-3:** Cl. 6: ao fim do desconto, a assinatura volta ao preço cheio informado na contratação. Cl. 4 e 7: cota e preço contratados valem até a troca de plano ou um reajuste com aviso de 30 dias. Upgrade: cota proporcional no ciclo, conforme a STORY-091.
- [ ] **CA-4:** Cl. 9: Meu plano também orienta sobre arrependimento quem comprou só créditos.
- [ ] **CA-5:** Política v2: cita bandeira e 4 últimos dígitos e descreve corretamente o que o provedor recebe; sem dizer que enviamos CPF, nome e e-mail se o checkout não os envia.
- [ ] **CA-6:** Um teste verifica os pontos-chave de cada cláusula.

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente
