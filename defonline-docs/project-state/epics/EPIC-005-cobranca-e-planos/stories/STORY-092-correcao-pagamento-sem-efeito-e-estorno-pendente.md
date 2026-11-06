---
story_id: STORY-092
slug: correcao-pagamento-sem-efeito-e-estorno-pendente
title: "Correção — pagamento sem efeito, estorno pendente e acesso em atraso"
epic_id: EPIC-005
sprint_id: null
type: bugfix
target_role: programador
requires_design: false
status: done
owner_agent: claude-sonnet-5-5 (programador)
created_at: 2026-11-05
updated_at: 2026-11-05
estimated_session_size: M
---

# STORY-092 — Correção — pagamento sem efeito, estorno pendente e acesso em atraso

Decisão: PDR-020. Origem: relatório `validation/report.md` da STORY-087 (rodada 1). Cada cenário `E*` citado está em `/private/tmp/claude-501/-Users-alexandro-Projetos-DEFOnline/a6214efa-1bd9-4daf-96bf-e8945355c6cf/scratchpad/v087/V087ExploratorioTest.php`: leve para a suíte como regressão, com o comportamento correto.

A suíte usa só o provedor Fake, que precisa imitar o provedor real nos pontos em que falhou: referência herdada ou ausente na 2ª cobrança, e data de vencimento só com o dia.

Defeitos: D-08, D-11a, D-12. Leia o detalhe e a reprodução no relatório.

## Critérios de aceite

- [x] **CA-1:** Todo pagamento confirmado sem efeito aparece na lista de devolução e gera alerta: diferença de upgrade paga depois da renovação (E4), duas diferenças pagas (E4b), checkout pago depois de cancelar (E21), segundo QR Code pago (E13). Cancelada ou não, uma assinatura não nasce sobre período vigente: E21 e E13 não concedem período a mais sem que o admin veja isso.
- [x] **CA-2:** Abrir um novo checkout do mesmo item cancela no provedor o checkout pendente anterior, quando o provedor permitir.
- [x] **CA-3:** Crédito com estorno em `aguardando_autorizacao` fica indisponível para consumo. Se o estorno for negado, o crédito volta (E12).
- [x] **CA-4:** Assinatura em atraso mantém análises e Raio X do Negócio durante as retentativas, como dizem a spec §3.7 e o Termo. O bloqueio só ocorre ao encerrar o ciclo (E17). A causa `EmAtraso` passa a ser aviso, sem bloqueio, e a régua segue igual.

## Padrões de qualidade

TDD, 98% em `app/Domain`, sem dado de cartão, sem termo do provedor fora do adaptador, datas por `DataExibicao`, interruptor respeitado.

## Notas do agente

### Entregue

Decisões e alternativas: IDR-032. Regressão: `app/tests/Feature/Cobranca/PagamentoSemEfeitoEEstornoPendenteTest.php` (24 testes), `app/tests/Domain/Cobranca/AvisoDeAtrasoTest.php`.

| CA | O que mudou | Testes (cenário da validação) |
|---|---|---|
| CA-1 | `PagamentoSemEfeito::registrar` (Invoice `paga` + `devolucao_motivo` + `Log::error` + auditoria) em: diferença de upgrade cuja base mudou (`TrocarDePlano::aplicarPaga`, com `plano_origem_id`/`ciclo_origem` na Invoice), segundo QR Code da renovação anual (`periodo_base` na Invoice da renovação), checkout `cancelada` que o provedor confirma como pago (`ConfirmarAssinatura::aplicar` e o ramo de créditos de `ProcessarEvento`). `Carteira::concederCota` trata a `cancelada` com ciclo por correr como vigente. Financeiro mostra os motivos novos. | E4, E4b (duas vezes: Invoice e lista "a devolver"), E21, E13, "checkout cancelado pago", idempotência, prévia do reembolso |
| CA-2 | `AbrirCheckoutDaInvoice::cancelarAnteriores`: o checkout pendente do mesmo item é cancelado no provedor; provedor que falha vira `Log::warning` e não impede o novo. | upgrade reaberto, créditos reabertos, item diferente intacto, provedor fora do ar |
| CA-3 | `LoteCredito::semEstornoEmCurso` na carteira e em Meu plano; `ReembolsarCobranca::negar()` desfaz a marca (auditoria + alerta `cobranca.reembolso_negado`). | E12 (bloqueia aguardando; volta ao negar), outro lote usado, webhook de estorno ainda retira |
| CA-4 | `RegrasConsumo::daAcesso` aceita `em_atraso`; o bloqueio vem quando a régua encerra o ciclo. `AvisoDeAtraso` na Home. | E17, cota sobrando, sem cota, régua até encerrar (3 retentativas + 48 h), aviso na Home, `RegrasConsumoTest` |

### Testes antigos que mudaram (contrato novo, não enfraquecimento)

- `RegrasConsumoTest`, `CarteiraTest`, `AcessoDrmTest`, `RenovacaoEFalhaDeCobrancaTest:166`: afirmavam que o atraso bloqueia na 1ª recusa (o defeito D-11a); agora afirmam acesso em atraso e bloqueio sem cota ou após o encerramento.
- `WebhookPagamentoTest` "compra cancelada ... pagamento tardio": continuava afirmando `status = cancelada` com dinheiro entrado; agora a Invoice fica `paga`, sem lote, marcada `checkout_cancelado` (CA-1).

### Decisões que o PO pode querer rever

- Em atraso e **sem cota**, `MotivoBloqueio::EmAtraso` continua bloqueando análise (o que destrava é o cartão). Com cota, só o aviso. O Raio X nunca bloqueia em atraso.
- Estorno `em_andamento` também tira o lote do consumo (a spec pede só `aguardando_autorizacao`).

### Pendências para a junção

- **STORY-088 CA-4** (estorno negado volta a paga): o tratamento do evento deve chamar `ReembolsarCobranca::negar($invoice->id)`; sem isso nada dispara a volta do crédito.
- O Fake continua deixando um checkout cancelado ser pago por simulação (é a corrida de um pagamento em voo); o cancelamento no provedor fica visível por `getCharge` (`cancelada`).
- `FinanceiroTest` "CA-2 lista e filtros" falha entre 21h e 24h de Brasília (D-13); correção na STORY-093.
