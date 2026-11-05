---
idr_id: IDR-018
slug: pagamentos-provedor-fake-webhook-e-fuso-do-banco
title: Pagamentos — namespace, provedor fake em cache, webhook fora do grupo web e data com offset
status: accepted
decided_at: 2026-11-05
decided_by: programador
owner_agent: claude-sonnet-5-5 (STORY-076)
related_story: STORY-076
related_adrs: ["ADR-010"]
related_idrs: ["IDR-017"]
supersedes: null
superseded_by: null
created_at: 2026-11-05
updated_at: 2026-11-05
---

# IDR-018 — Pagamentos: namespace, provedor fake, webhook e fuso

## Contexto

A STORY-076 cria a base de pagamento que as estórias 077, 078, 079, 083 e 087 vão usar. O ADR-010 (ainda `proposed`, recomendações adotadas pelo orquestrador) define a interface; faltavam escolhas de implementação que outros agentes precisam conhecer.

## Decisão

> **Interface, DTOs e enums em `App\Domain\Cobranca`; adaptadores em `App\Infrastructure\Cobranca\{Asaas,Fake}`; webhook em `POST /webhooks/pagamentos` fora de sessão e CSRF; processamento por job; datas gravadas com offset.**

- `PaymentProvider` (seleção por `services.payments.provider`, `fake|asaas`, recusa `fake` em produção). Adaptador do Asaas recusa subir sem `ASAAS_BASE_URL` (sem padrão) ou com chave de outro ambiente (prefixo `$aact_hmlg_` x `$aact_prod_`).
- **Fake** guarda estado no cache (compartilhado com o servidor do Dusk) e tem gatilhos: `falharProximoCheckout()`, `simularPagamento/Expiracao/Cancelamento()`. A "página do provedor" fake (`/pagamentos/fake/{invoice}`) só existe com o provedor fake fora de produção.
- **Webhook:** `ReceberWebhook` autentica via `parseWebhook`, grava em `provider_events` (`UNIQUE (provedor, evento_id)`, `insertOrIgnore`) e enfileira `ProcessarEventoDoProvedor`. Duplicado já tratado é ignorado; duplicado parado em `recebido|erro` é reenfileirado; `cobranca:reprocessar-eventos` (agendado a cada 5 min) retoma o resto. A rota remove `StartSession`, `PreventRequestForgery` etc. (não cria linha de sessão por webhook).
- **Crédito só em `cobranca.paga`**, depois de `getCharge` (estado atual manda). `Invoice` vira `paga` e `Carteira::concederCreditos` roda na mesma transação sob lock da linha; `lotes_credito.invoice_id` tem índice único parcial. `vencida` ainda aceita a confirmação do banco; `cancelada`, `estornada` e `contestada` são finais.
- **Datas:** `Invoice`, `ProviderEvent` e `LoteCredito` usam `$dateFormat = 'Y-m-d H:i:sP'`.

## Por quê

O banco local roda em `America/Sao_Paulo` e a aplicação em UTC: sem offset, o Postgres interpreta o horário gravado no fuso do servidor e desloca 3 horas (visto com `checkout_expira_em`). O padrão já existia em `Recomendacao`. Produção (UTC) não é afetada, mas os testes locais seriam enganosos.

## Alternativas consideradas

- **Fake em memória:** não funciona com o Dusk (servidor em outro processo).
- **Processar o webhook dentro da requisição:** simples, mas foge do ADR-010 e arrisca estourar os 10 s do Asaas se `getCharge` for lento.
- **Mudar o fuso do banco/aplicação:** fora do escopo e afeta tudo.

## Consequências

### Para outros agentes
- Novos modelos com `timestamptz` gravados pela aplicação devem usar `$dateFormat` com offset (`Assinatura` e `Cupom*` ainda não: o deslocamento só aparece no banco local).
- Novas rotas de webhook seguem o padrão `withoutMiddleware` de `routes/web.php`.
- Tipos do Asaas nunca fora de `Infrastructure/Cobranca/Asaas` (teste de arquitetura `PagamentoArchTest`).
- Teste contra o sandbox: `pest tests/Sandbox --group=asaas-sandbox`, com `ASAAS_SANDBOX_API_KEY` no ambiente.

### Trade-offs aceitos
- Correlação do pagamento com a Invoice depende de `externalReference` ou `checkoutSession` na cobrança do Asaas (ADR-010 pendência 1: não confirmado com pagamento real; o código tenta os dois e a consulta `getCharge`).

## Como verificar

`tests/Unit/Arch/PagamentoArchTest.php`, `tests/Feature/Cobranca/WebhookPagamentoTest.php` e `tests/Browser/CompraCreditosPixBrowserTest.php`.
