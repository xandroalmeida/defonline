---
idr_id: IDR-027
slug: reembolso-no-backoffice-e-pagamento-sem-destino
title: Reembolso total pelo backoffice, com trava antes da chamada de rede, e pagamento sem destino como Invoice marcada para devolver
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-085)
related_story: STORY-085
related_adrs: ["ADR-010"]
related_idrs: ["IDR-018", "IDR-019", "IDR-020", "IDR-021"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-027 — Reembolso no backoffice

## Contexto

A STORY-085 dá à equipe a tela Financeiro (`financeiro.gerenciar`): reembolso, nota fiscal e destaque do que precisa de olhar humano. O ADR-010 (`proposed`, recomendações adotadas pelo orquestrador) manda: estorno de cartão total pela API, Pix em `aguardando_autorizacao` (o admin autoriza no painel do Asaas e o estado muda pelo evento), contestação em destaque. As STORY-079 e 081 deixaram dois casos de "pagamento sem destino" só em `Log::error`.

## Decisão

- **Porta:** `PaymentProvider::refund(ReferenciaProvedor, ?int $valorCentavos, string $motivo): ReembolsoNoProvedor` com `ResultadoReembolso` = `concluido | aguardando_autorizacao | em_andamento | recusado`. (`em_andamento` é a adição ao ADR: o Asaas aceita e ainda não concluiu, `REFUND_IN_PROGRESS`.) A tela só pede estorno total; o valor opcional existe pelo contrato do ADR. 4xx do provedor = `recusado` (com a descrição dele); rede e 5xx = `ProvedorIndisponivel` (resultado desconhecido).
- **Não reembolsar duas vezes:** a trava é `invoices.reembolso_estado`, gravada sob o lock da linha ANTES da chamada de rede (que fica fora da transação). Recusa ou indisponibilidade a devolvem a nulo; `concluido`, `aguardando_autorizacao` e `em_andamento` barram novo pedido. Antes de estornar, `getCharge`: cobrança que o provedor já mostra estornada (painel, ou resposta perdida) é só finalizada, sem segundo estorno.
- **"Estornada" só com o provedor confirmando:** cartão responde `concluido` na hora; Pix e `em_andamento` ficam como estão (cobrança `paga`) até o webhook `cobranca.estornada`, que o `ProcessarEvento` confere por `getCharge` e conclui pelo mesmo `ReembolsarCobranca::concluir`. Estorno feito direto no painel do provedor também entra por aí.
- **O que se retira ao concluir:** (a) créditos: o saldo restante do lote vira 0 e o lote ganha o estado `estornado` (novo, para não confundir com esgotado); o já consumido não volta e o saldo nunca fica negativo; a tela mostra quantos já foram usados antes de confirmar; (b) assinatura: encerrada (`expirada`), cota restante zerada, `encerramento_pendente_no_provedor` ligado para o job da régua cancelar a recorrência (e repetir se o provedor cair). Se há cobrança paga MAIS RECENTE na mesma assinatura, o acesso é dela e não é tocado (estornar a de um mês antigo não derruba o plano atual). Estornar uma renovação encerra a assinatura inteira.
- **Pagamento sem destino:** `ConfirmarAssinatura` passa a registrar uma Invoice `paga`, `renovacao=false`, com `devolucao_motivo` = `assinatura_cancelada` (pago depois do cancelamento ou do encerramento em atraso), `assinatura_antiga` (cobrança em voo da assinatura trocada) ou `assinatura_duplicada` (dois checkouts). Nada é concedido; a tela destaca numa seção "Atenção", a prévia diz "só o dinheiro é devolvido". Idempotente por `UNIQUE (provedor, id_externo)`. Os `Log::error` existentes continuam.
- **Contestação:** o evento marca a Invoice `contestada` (estado final), alerta (`cobranca.contestada`) e aparece em destaque; o acesso NÃO é retirado sozinho (pode ser revertida; decisão do admin).
- **Nota fiscal:** `nf_emitida_em/por/numero` na Invoice; só cobrança `paga`; marcar duas vezes é recusado; cobranças com reembolso em curso ou a devolver não entram na lista "sem nota".
- **Auditoria (CA-7):** `cobranca.reembolso_solicitado`, `_concluido` (com o que foi retirado e os créditos já usados), `_nao_realizado`, `_aceito_pelo_provedor` e `cobranca.nota_fiscal_emitida`, com o admin como ator e o dono da conta em `usuario_id`.
- **CA-8:** nada novo: `ExigePermissao` já nega o backoffice inteiro (404) em sessão de "Logar como", em toda requisição Livewire.

## Verificação no sandbox do Asaas (2026-11-05, `tests/Sandbox`, grupo `asaas-sandbox`)

- **Confirmado:** cobrança de cartão (`POST /payments` com o cartão de teste público) estorna por inteiro (`REFUNDED`); a leitura seguinte dá `estornada`; o segundo pedido é recusado (4xx → `recusado`).
- **Confirmado:** Pix confirmado por `POST /sandbox/payment/{id}/confirm` e estornado fica `AWAITING_CRITICAL_ACTION_AUTHORIZATION` (`aguardando_autorizacao`).
- **Achado novo:** cobrança recém-criada recusa o estorno com 400 ("Não é possível solicitar estorno para essa cobrança no momento. Tente novamente em alguns instantes."). O teste repete até assentar (alguns segundos). Na tela isso aparece como recusa, a trava é liberada e o admin pode repetir.
- **Não confirmado:** que o webhook de estorno chegue com `PAYMENT_REFUNDED` e na ordem esperada (exige URL pública, ADR-010 pendência 1); o formato real de `refunds[]` do estorno parcial (não usado).

## Consequências

- Migration `2026_11_05_300085_operacao_financeira`. A `STORY-087` pode contar com `devolucao_motivo`, `reembolso_*` e `nf_*` nas Invoices.
- Provedor trocado: basta implementar `refund` e mapear os resultados; a tela não muda.
