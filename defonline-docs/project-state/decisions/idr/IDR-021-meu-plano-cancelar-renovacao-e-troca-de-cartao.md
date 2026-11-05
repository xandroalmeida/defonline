---
idr_id: IDR-021
slug: meu-plano-cancelar-renovacao-e-troca-de-cartao
title: Meu plano — cancelar é suspender a recorrência, e trocar o cartão é um novo checkout com a antiga cancelada só depois da confirmação
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-081)
related_story: STORY-081
related_adrs: ["ADR-010"]
related_idrs: ["IDR-019"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-021 — Cancelar a renovação e trocar o cartão

## Contexto

A STORY-081 dá ao usuário "Cancelar renovação", "Reativar renovação" e "Trocar cartão". O ADR-010 (`proposed`, recomendações adotadas pelo orquestrador) manda trocar o cartão por um novo Checkout recorrente e só cancelar a assinatura antiga depois da confirmação. A STORY-079 (renovação e falha de cobrança) corre em paralelo e dona da máquina de estados de atraso.

## Decisão

- **Cancelar renovação = `suspendSubscription` no provedor + `Carteira::mudarStatus(Cancelada)`**; reativar = `resumeSubscription` + `Ativa` (apaga `data_cancelamento`). A assinatura NÃO é apagada no provedor, porque reativar (CA-5) precisa religá-la sem novo cartão. O provedor é chamado antes de a carteira mudar: se falhar, nada muda e a tela diz o estado real. `proximo_vencimento` e a cota não mudam; o acesso segue até o fim do ciclo (`RegrasConsumo` já tratava `Cancelada`).
- **`Ativa` cancela a renovação (suspende); `EmAtraso` também cancela, mas encerra de vez** (decisão do PO, pós-junção com a 079): `cancelSubscription`, status `Expirada`, acesso bloqueado na hora, régua parada, histórico preservado. `Cancelada` com ciclo por correr conta como assinatura vigente em `IniciarAssinatura::assinaturaVigente`, para ninguém pagar duas vezes o mesmo período.
- **Troca de cartão = tabela `trocas_de_cartao`** (não é `Invoice`: não é cobrança, e assim nenhuma soma de receita a conta). `iniciar` abre um Checkout `RECURRENT` com o valor da assinatura; em dia, a 1ª cobrança é no vencimento já pago (`PedidoCheckout::primeiraCobrancaEm`, `nextDueDate`); em atraso, é hoje (`cobra_agora`). Nada muda na assinatura até o provedor confirmar.
- **Confirmação** = `checkout.concluido` com o id da assinatura nova, ou a 1ª `cobranca.paga` da assinatura nova (o que chegar primeiro; `confirmar` é idempotente). Então `assinaturas.id_externo` passa a apontar para a nova e a antiga é cancelada (`cancelSubscription`). Se o cancelamento falhar, `Log::error` e `assinaturas:cancelar-antigas` (horário) repete até conseguir. Troca expirada/cancelada na nossa ponta ainda é confirmada se o provedor criou a assinatura (senão ela cobraria em dobro, órfã).
- **Recuperar atraso**: o pagamento da 1ª cobrança de uma troca `cobra_agora` volta a assinatura a `Ativa` e renova o ciclo pelo mesmo caminho da renovação (`ConfirmarAssinatura::renovar`). Pós-junção, o parâmetro `recuperaAtraso` foi removido: a 079 restabelece em qualquer pagamento em atraso, e a troca concluída em atraso zera a régua da cobrança antiga.
- **Plano sem recorrência no provedor** (`id_externo` nulo — o Pix anual da 080): sem cancelar nem trocar cartão; a tela diz até quando vai o acesso (ajuste do ADR-010).
- Porta `PaymentProvider` ganha `cancelSubscription`, `suspendSubscription`, `resumeSubscription` (o ADR-010 previa só `cancelSubscription`).

## Verificação no sandbox do Asaas (2026-11-05, sem pagamento real; dados de teste removidos)

- **Confirmado:** `PUT /subscriptions/{id}` com `{"status":"INACTIVE"}` e depois `{"status":"ACTIVE"}` responde 200 e o `GET` seguinte reflete cada estado (testado numa assinatura BOLETO, que tem a mesma máquina de status da de cartão). `DELETE` responde 200 com `deleted=true`. Suspender/religar está, portanto, correto no adaptador.
- **Confirmado:** `POST /checkouts` RECURRENT/CREDIT_CARD aceita `nextDueDate` hoje e daqui a 3 dias (200, mesmas chaves de resposta), e o `POST /checkouts/{id}/cancel` funciona. Antes de alguém pagar, nenhuma cobrança é criada (0 cobranças por `externalReference`), seja a data hoje ou futura.
- **NÃO foi possível confirmar sem pagar:** se, ao concluir o checkout com `nextDueDate` futuro, o cartão é cobrado na hora (risco de cobrança em dobro na troca em dia) ou só na data. O `GET /checkouts/{id}` responde 404 no sandbox, então nem o estado do checkout é consultável. O sandbox, portanto, não mostrou risco, mas também não o descartou. Passo para fechar: pagar um checkout com `nextDueDate` futuro com o cartão de teste (`4539 6560 4839 2220`) e listar `/payments?subscription=...` (exige URL pública de webhook, ADR-010 pendência 1). **Até lá, não ligar a cobrança em produção sem esse teste.** O fluxo não foi alterado: não há evidência para escolher outro desenho.
- **Não confirmado:** religar (`ACTIVE`) uma assinatura que passou do vencimento enquanto suspensa gera cobrança retroativa? O fluxo só reativa dentro do ciclo pago, então a data ainda não passou; o caso fica de fora.
- **Não confirmado:** `CHECKOUT_PAID` trazer `checkout.subscription` (precisa de pagamento). Sem ele a troca fica `pendente` com alerta `cobranca.troca_de_cartao_sem_assinatura`.

## Consequências

- 079: `ConfirmarAssinatura::renovar` ganhou o parâmetro `recuperaAtraso`; `Carteira::mudarStatus(Ativa)` limpa `data_cancelamento`.
- 082 (troca de plano): `assinaturas.id_externo` pode mudar por troca de cartão; use sempre o valor atual.
