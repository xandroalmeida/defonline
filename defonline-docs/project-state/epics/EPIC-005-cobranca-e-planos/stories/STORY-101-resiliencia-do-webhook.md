---
story_id: STORY-101
slug: resiliencia-do-webhook
title: "Pagamento com o webhook fora: conferência na tela e alertas"
epic_id: EPIC-005
sprint_id: null
type: feature
target_role: programador
requires_design: false
status: in_review
owner_agent: claude-opus-5-5
created_at: 2026-11-06
updated_at: 2026-11-06
estimated_session_size: M
---

# STORY-101 — Pagamento com o webhook fora: conferência na tela e alertas

Origem: pedido do PO em 2026-11-06. A conciliação da STORY-079 já recupera pagamentos, mas pode levar até cerca de 1h15 e não avisa ninguém.

## Critérios de aceite

- [x] **CA-1:** Nas telas de pagamento, assinatura e troca de plano, depois de 1 minuto de espera, o sistema consulta o provedor no máximo a cada 30 s por cobrança. O pagamento encontrado é aplicado uma vez só, pelo fluxo da conciliação.
- [x] **CA-2:** Pagamento encontrado nessa consulta gera alerta à equipe. Provedor fora não muda a tela nem a cobrança.
- [x] **CA-3:** A cada 5 minutos, o sistema confere o webhook com a nossa URL. Fila pausada gera alerta P1 e é retomada. Webhook desligado ou sem cadastro em produção gera alerta P1, sem religar. Cadastros de outras URLs não são tocados.
- [x] **CA-4:** Conciliação que corrige algo gera alerta à equipe.
- [x] **CA-5:** Agendador sem batimento por mais de 10 minutos gera alerta P1. A conferência roda depois da resposta do `/health`, e o healthcheck do contêiner web o chama a cada 10 s.

## Configuração

`PAYMENTS_WEBHOOK_URL` só é necessária quando a URL cadastrada não é `APP_URL/webhooks/pagamentos`.

## Testes

- `tests/Feature/Cobranca/ResilienciaDoWebhookTest.php`
