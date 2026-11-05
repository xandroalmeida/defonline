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
- **Só `Ativa` cancela.** Em atraso o caminho é trocar o cartão (a 079 decide o resto). `Cancelada` com ciclo por correr conta como assinatura vigente em `IniciarAssinatura::assinaturaVigente`, para ninguém pagar duas vezes o mesmo período.
- **Troca de cartão = tabela `trocas_de_cartao`** (não é `Invoice`: não é cobrança, e assim nenhuma soma de receita a conta). `iniciar` abre um Checkout `RECURRENT` com o valor da assinatura; em dia, a 1ª cobrança é no vencimento já pago (`PedidoCheckout::primeiraCobrancaEm`, `nextDueDate`); em atraso, é hoje (`cobra_agora`). Nada muda na assinatura até o provedor confirmar.
- **Confirmação** = `checkout.concluido` com o id da assinatura nova, ou a 1ª `cobranca.paga` da assinatura nova (o que chegar primeiro; `confirmar` é idempotente). Então `assinaturas.id_externo` passa a apontar para a nova e a antiga é cancelada (`cancelSubscription`). Se o cancelamento falhar, `Log::error` e `assinaturas:cancelar-antigas` (horário) repete até conseguir. Troca expirada/cancelada na nossa ponta ainda é confirmada se o provedor criou a assinatura (senão ela cobraria em dobro, órfã).
- **Recuperar atraso**: o pagamento da 1ª cobrança de uma troca `cobra_agora` volta a assinatura a `Ativa` e renova o ciclo pelo mesmo caminho da renovação (`ConfirmarAssinatura::renovar(recuperaAtraso: true)`). Qualquer outro pagamento em atraso continua ignorado com alerta, como a 079 manda. Quando a 079 mudar essa regra, basta remover o parâmetro.
- **Plano sem recorrência no provedor** (`id_externo` nulo — o Pix anual da 080): sem cancelar nem trocar cartão; a tela diz até quando vai o acesso (ajuste do ADR-010).
- Porta `PaymentProvider` ganha `cancelSubscription`, `suspendSubscription`, `resumeSubscription` (o ADR-010 previa só `cancelSubscription`).

## Pendências (não verificadas contra o Asaas real)

1. `PUT /subscriptions/{id}` aceitar `status` (`INACTIVE`/`ACTIVE`) e religar não gerar cobrança retroativa.
2. Checkout `RECURRENT` com `nextDueDate` futuro: se NÃO cobra no ato (senão a troca em dia cobra em dobro).
3. `CHECKOUT_PAID` trazer `checkout.subscription`; sem isso a troca fica `pendente` com alerta `cobranca.troca_de_cartao_sem_assinatura`.

## Consequências

- 079: `ConfirmarAssinatura::renovar` ganhou o parâmetro `recuperaAtraso`; `Carteira::mudarStatus(Ativa)` limpa `data_cancelamento`.
- 082 (troca de plano): `assinaturas.id_externo` pode mudar por troca de cartão; use sempre o valor atual.
