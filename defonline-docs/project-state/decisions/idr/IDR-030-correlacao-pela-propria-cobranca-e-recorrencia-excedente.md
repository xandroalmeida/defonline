---
idr_id: IDR-030
slug: correlacao-pela-propria-cobranca-e-recorrencia-excedente
title: Cobrança correlacionada pelo próprio id, recorrência excedente cancelada e devolução, e eventos de estorno parcial, negado e contestação revertida
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-088)
related_story: STORY-088
related_adrs: ["ADR-010"]
related_idrs: ["IDR-019", "IDR-020", "IDR-027", "IDR-028"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-030 — Correlação pela própria cobrança e recorrência excedente

## Contexto

A validação do EPIC-005 (D-01, D-02, D-03, D-16) mostrou que, numa recorrência, o provedor pode repassar a nossa referência e o checkout da 1ª cobrança às seguintes (não confirmado no sandbox). Correlacionar por referência primeiro fazia a renovação paga ser ignorada após contestação ou estorno do mês 1, o estorno do mês 2 cair na Invoice do mês 1, e a recorrência de um checkout duplicado seguir cobrando sem registro.

## Decisão

- **Correlação:** `ProcessarEvento::localizar` procura primeiro a Invoice cuja `id_externo` é a cobrança do evento. Referência e checkout só valem para uma Invoice que ainda não tem cobrança ou que tem a mesma. Vale com ou sem repasse de `externalReference`.
- **Recorrência excedente:** o pagamento duplicado de assinatura grava `invoices.assinatura_id_externo` (a recorrência do provedor) e, depois da transação, `ProcessarEvento` cancela essa recorrência no provedor. Se o provedor falhar, o evento vai para `erro` e cada repetição cancela de novo (idempotente: 404 é sucesso). A cobrança do mês seguinte dessa recorrência vira uma Invoice `paga` com `devolucao_motivo` e entra na lista da STORY-085, sem renovar nada.
- **Sem destino nunca é silencioso:** pagamento confirmado de um checkout já cancelado, estornado ou contestado vira Invoice com `devolucao_motivo = checkout_encerrado` (novo valor do CHECK), com alerta, e a recorrência é cancelada. Pagamento de assinatura desconhecida e sem dono (nenhuma Invoice liga a cobrança a um usuário) segue só como `Log::error` `cobranca.evento_sem_invoice`, que gera alerta da equipe: sem usuário não há Invoice a criar.
- **Novos eventos** (`TipoEvento`): `ContestacaoRevertida` (`PAYMENT_CHARGEBACK_REVERSED`: a Invoice `contestada` volta a `paga`, se o provedor mostra a cobrança paga), `EstornoParcial` (`PAYMENT_PARTIALLY_REFUNDED`: grava `invoices.reembolsado_parcial_centavos`, a cobrança segue `paga`, sem retirar acesso, com alerta), `EstornoNegado` (`PAYMENT_REFUND_DENIED`: libera `reembolso_estado`, a cobrança segue `paga`, com alerta; é o ponto onde a pista C liga `ReembolsarCobranca::negar`), `CobrancaRestaurada` (`PAYMENT_RESTORED`: tratamento explícito sem efeito, já que `PAYMENT_DELETED` também não altera a Invoice). O valor do estorno parcial vem do adaptador (`refunds[].value` somado, em centavos) em `provider_events.valor_estornado_centavos`; sem a lista, fica desconhecido e nada se inventa.
- **Fake mais fiel:** `FakePaymentProvider::herdarReferenciaNasRenovacoes()` faz a cobrança de renovação repassar a referência da 1ª, e o webhook do Fake passa a carregar a referência da cobrança.

## Não confirmado no sandbox

Os nomes dos quatro eventos vêm da lista de eventos do Asaas; o formato de `refunds[]` no estorno parcial e se `PAYMENT_CHARGEBACK_REVERSED` deixa a cobrança como `RECEIVED` não foram vistos. Se o estado vier diferente de pago, a reversão fica `ignorada` e a Invoice continua contestada, para a equipe olhar.

## Consequências

- Migration `2026_11_05_500088_recorrencia_e_estorno_parcial` (reversível).
- A STORY-092 (pagamento sem efeito e estorno pendente) herda `registrarPagamentoSemDestino` com `assinatura_id_externo` e o ponto único `ProcessarEvento::negarEstorno`.
