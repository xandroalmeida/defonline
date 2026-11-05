---
idr_id: IDR-019
slug: assinatura-invoice-por-cobranca-e-cota-mensal-do-anual
title: Assinatura — uma Invoice por cobrança, vencimento ancorado no início e cota mensal do ciclo anual
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-077)
related_story: STORY-077
related_adrs: ["ADR-010"]
related_idrs: ["IDR-017", "IDR-018"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-019 — Assinatura: Invoice por cobrança e cota mensal do anual

## Contexto

A STORY-077 ativa e renova assinaturas por webhook. Quem trata falha de renovação (079), troca de plano (082) e estorno (087) herda estas escolhas.

## Decisão

- **Uma Invoice por cobrança paga**, inclusive renovações (`renovacao = true`, `id_externo` = cobrança do provedor, `UNIQUE (provedor, id_externo)`). É isso que torna a renovação idempotente (CA-8). A Invoice do checkout (`tipo = assinatura`) é a 1ª.
- **Elo com o provedor:** `assinaturas.provedor` + `assinaturas.id_externo` (id da assinatura no provedor, `UNIQUE` parcial); `CobrancaNoProvedor`/`EventoDoProvedor` ganham `assinaturaId`. Renovação = cobrança paga cuja assinatura é nossa e que ainda não é Invoice.
- **Vencimento sem deriva:** `proximo_vencimento = data_inicio + meses(ciclo) * periodos_pagos`; o aniversário da cota é `data_inicio + n meses` (`CotaMensal`), com `addMonthsNoOverflow` (31/01 -> 28/02 -> 31/03).
- **Cota do anual:** acesso de 12 meses pagos; `assinaturas.proxima_cota_em` agenda o próximo aniversário e `assinaturas:renovar-cota` (horário, idempotente) devolve a cota cheia, sem acumular. Mensal renova a cota junto com o pagamento (`Carteira::renovarCota`).
- **Renovação só em assinatura `ativa`.** Atraso, cancelada e reativação são da 079.
- Cartão: só `cartao_bandeira` e `cartao_ultimos4`, quando o provedor informa.

## Consequências

- Para a 079: pagamento de assinatura `em_atraso` hoje é ignorado com `Log::error cobranca.renovacao_de_assinatura_inativa`.
- Para a 082: trocar de plano precisa decidir o que fazer com `id_externo` (nova assinatura no provedor) e `periodos_pagos`.
- `Assinatura` passou a usar `$dateFormat` com offset (IDR-018).
