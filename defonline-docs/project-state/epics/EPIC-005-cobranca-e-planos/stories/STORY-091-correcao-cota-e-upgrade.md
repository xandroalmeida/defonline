---
story_id: STORY-091
slug: correcao-cota-e-upgrade
title: "Correção — cota do anual cancelado, cota contratada e upgrade proporcional"
epic_id: EPIC-005
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: done
owner_agent: claude-sonnet-5-5 (pista B)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-091 — Correção — cota do anual cancelado, cota contratada e upgrade proporcional

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-05, D-07, D-19. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [x] **CA-1:** Assinatura anual cancelada, dentro do período pago, continua recebendo a cota mensal até o fim do ano pago, conforme a cláusula 8 do Termo (E25).
- [x] **CA-2:** A cota contratada é retrato: mudança no catálogo não reduz a cota de quem já assina até ele trocar de plano (E16b). Isso se aplica a preço e cota, e não há reajuste automático.
- [x] **CA-3:** **Decisão do PO em 2026-11-05 (D-05), antes provisória:** no upgrade, a cota extra do ciclo em curso é proporcional aos dias restantes, com arredondamento para baixo e nunca menor que a cota já disponível no plano antigo. A cota cheia do plano novo vale da próxima renovação. A tela de revisão mostra as duas informações. Básico para Pro a 12 horas do fim não dá 9 análises extras (E6, E6b).
- [x] **CA-4:** Repetir upgrade e downgrade não acumula cota nem reduz o preço pago (E6).

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente

**Lido:** estória, `validation/report.md` (D-05, D-07, D-19), IDR-019, 021, 026, 029, exploratório (E6, E6b, E16b, E25).

**Feito (TDD; os cenários E* entraram na suíte com o comportamento correto):**
- **CA-1** (`Carteira::renovarCotasMensais`): `ativa` e `cancelada` recebem a cota do anual; a `proxima_cota_em` nunca passa do fim do ano pago. Em atraso/encerrada segue sem. Teste: `CorrecaoCotaEUpgradeTest` (E25, ano inteiro: 11 voltas, depois nada, e em atraso).
- **CA-2** (`Domain/Cobranca/CotaContratada`, usado em `ConfirmarAssinatura::renovar`, `CiclosGratuitos`, `IniciarRenovacaoPix`): a renovação mantém o `cota_snapshot`; o catálogo de hoje só vale se a troca agendada muda de plano. Teste: E16b, cota que sobe, downgrade com catálogo de hoje. **Mudança em teste antigo:** `RenovacaoEFalhaDeCobrancaTest` "CA-2 renovação paga" afirmava a cota do catálogo de hoje (comportamento que o D-19 condena); agora afirma o retrato. A parte "o preço contratado segue" ficou intacta.
- **CA-3** (regra provisória, UM ponto: `Domain/Cobranca/CotaExtraNoUpgrade::calcular`): extra = `floor((cotaNova − cotaAntiga) × restante/janela)`, nunca negativo. A janela é a da cota em curso (`CotaMensal::janela`): o mês no mensal, o mês da cota no anual (não o ano). Mudança de ciclo (período novo) dá a cota cheia já. `cota_snapshot` passa à cota cheia do plano novo, que volta na virada. A prévia (`PreviaDeTroca::cotaExtraAgora`, `cotaCheiaEm`) e as telas de revisão e de confirmação mostram as duas informações. Invoice `troca_de_plano` guarda `cota_extra_troca` (migration `2026_11_05_500091`): paga-se e recebe-se o que a revisão mostrou, mesmo com o pagamento tardio. Testes: `CotaDoUpgradeTest` (domínio), E6/E6b e demais em `CorrecaoCotaEUpgradeTest`, telas em `TrocarPlanoTest`/`TrocaDePlanoBrowserTest`. Testes antigos de `TrocaDePlanoTest` que esperavam a cota cheia na hora foram atualizados (7 = 1 + floor(9×20/30)).
- **CA-4**: agendar e desfazer 4 vezes não muda cota, preço no provedor nem total pago; subir e descer + renovação deixa a cota do destino sem sobra. Teste: `CorrecaoCotaEUpgradeTest`.

**Decisão do PO (2026-11-05): a regra proporcional está aprovada e deixa de ser provisória.** Observação: a cobrança do upgrade segue proporcional ao tempo (inclusive abaixo de R$ 5,00 sem cobrança, ADR-010); só a cota mudou. Em E6b o usuário paga R$ 14,91 e não recebe análise extra nesse ciclo, só a cota cheia na virada. A regra continua isolada em `CotaExtraNoUpgrade`.
