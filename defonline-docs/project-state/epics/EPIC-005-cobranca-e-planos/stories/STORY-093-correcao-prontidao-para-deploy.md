---
story_id: STORY-093
slug: correcao-prontidao-para-deploy
title: "Correção — prontidão para deploy"
epic_id: EPIC-005
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: in_review
owner_agent: programador
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-093 — Correção — prontidão para deploy

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-10, D-13, D-14, D-17, D-18, D-21. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [x] **CA-1:** Sem provedor configurado e com a cobrança desligada, os agendadores saem com sucesso sem agir, e o webhook responde sem erro 500, em `APP_ENV=production` (E22). Com a cobrança ligada e sem provedor válido, o sistema recusa ligar o interruptor e explica o motivo.
- [x] **CA-2:** O filtro de data do Financeiro usa `DataExibicao`, e `FinanceiroTest` passa em qualquer horário, com teste de relógio congelado às 22:30.
- [x] **CA-3:** `database-schema.md` regenerado pela skill `analisar-banco` local (só local), frontmatter das estórias coerente com o índice, `.env.example` completo, e `ZALKVPS.md` ou README descrevem as variáveis e o webhook de cobrança.
- [x] **CA-4:** Tentativas de cupom têm limite por conta e por IP (D-17).
- [x] **CA-5:** O payload guardado do provedor remove `creditCardHolderInfo` e qualquer dado do titular (E14).
- [x] **CA-6:** Chamadas de rede ao provedor não ocorrem dentro de transação com linha travada; se o provedor falhar, o estado local fica consistente e é reconciliado.

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente

**Programador, 2026-11-05.** Entregue em main, com commits separados.

- **Integração antes da 093:** a junção deixou `invoices_devolucao_motivo_ck` sem os motivos da 092 (as migrations 300092 e 500088 trocavam a mesma constraint e a posterior apagava a outra); migration `600093` reúne os sete. O estorno negado chama `ReembolsarCobranca::negar`; checkout cancelado pago tem um destino só (a própria Invoice, `checkout_cancelado`), cancela a recorrência e a cobrança do mês seguinte também vai à devolução; o alerta de pagamento sem destino só sai quando a Invoice de devolução nasce. `agendar` e `desfazer` usam `alvoNoProvedor` (fim do desconto, valor cheio). Janela da cota (`CotaMensal::janela`) usa o fim nominal quando o aniversário cai no último dia (091 × 089); o E6b mede os 3 dias a partir do ciclo nominal.
- **CA-1:** `ProvedorNaoConfigurado` entra quando `PAYMENTS_PROVIDER` não está definido em produção ou o Asaas está com configuração incompleta; agendadores saem com sucesso (trait `SemProvedorNaoAge`), webhook responde 401, interruptor recusa ligar. Valor explícito errado (typo, `fake` em produção) continua estourando, por decisão (erro de deploy). Teste: `ProntidaoParaDeployTest`.
- **CA-2:** `Financeiro` já usava `DataExibicao::fuso()` (commit da 090); acrescentei o teste às 22:30 (`FinanceiroTest`).
- **CA-3:** `database-schema.md` regenerado do banco local (46 migrations, 42 tabelas); `.env.example` completo; README com variáveis e webhook; frontmatter alinhado ao índice (done/blocked; 088 a 092 seguem `in_review`, o índice ainda diz `ready`).
- **CA-4:** 10 códigos errados por conta e 30 por IP por hora (`ComCupom`). **CA-5:** chaves do titular removidas do payload (`AsaasPaymentProvider`). **CA-6:** `GerirRenovacao` (3 tempos, desfaz no provedor se a assinatura mudou) e ajuste de valor da troca de plano após o commit (marca `ajuste_provedor_pendente` + rotina horária); o `FakePaymentProvider` recusa chamada dentro de transação nos testes de Feature.
