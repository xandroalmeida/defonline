---
runbook_id: RFB-capacity-planning
slug: rfb-capacity-planning
title: Capacity planning dos RPM default da abstração RFB
owner: Arquiteto
related_idrs: ["IDR-004", "IDR-005", "IDR-006"]
related_adrs: ["ADR-002", "ADR-004"]
related_stories: ["STORY-015", "STORY-018"]
created_at: 2026-05-23
updated_at: 2026-05-23
---

# Capacity planning — RPM default dos provedores RFB

## Pergunta a responder

Os defaults conservadores de **3 RPM** por provedor (IDR-004) **aguentam** a WAVE-2026-01 sem disparar `erro_5xx` por rate-limit? E o que recomendar quando a operação contratar plano pago?

## Volume esperado da WAVE-2026-01

| Dimensão | Valor | Fonte |
|---|---|---|
| Modo de acesso | Beta fechado, **acesso por convite** | PDR-001 §"Opção 1" (escolhida) |
| Setor coberto | Apenas Indústria (Roberto) | PDR-001 |
| Capacidade arquitetural projetada (teto, não esperado) | 300 usuários concorrentes | NRF §1.3 |
| Cadastros esperados na onda (estimativa do Arquiteto) | **≤ 100 cadastros** no horizonte de 12 semanas | derivado de "beta fechado por convite" + escopo Indústria + ausência de comercial ativo |
| Consultas RFB por cadastro | **1** chamada (CNPJ ↦ 7 campos da espec §3.3 Passo 2) | espec funcional + NRF §3.1 |
| Cache hit rate esperado | **~0%** no MVP (cadastros são novos; reedição do mesmo CNPJ raríssima) | hipótese conservadora |

**Pico hipotético realista** (não cenário-base): convite massivo no Telegram da EBC Parcerias com 30 convidados acessando em 10 minutos seguidos → **30 consultas em 600s** ≈ **3 RPM sustentado** durante o pico.

## Cálculo contra o default de 3 RPM por provider

| Cenário | Demanda | Default (3 RPM) | Veredicto |
|---|---|---|---|
| **Volume médio da onda** (100 cadastros / 12 semanas) | ≈ 0,01 RPM agregado | 3 RPM | ✅ folga de 300× |
| **Pico hipotético** (30 convites simultâneos em 10 min) | 3 RPM sustentado | 3 RPM | ⚠️ no limite exato — qualquer ruído (retentativa, F5 do usuário) bate no rate-limit, gerando `erro_5xx` esporádico (fallback transparente da NRF §3.1 absorve a UX). |
| **Pior caso** (todos os 300 concorrentes da NRF §1.3 cadastrando ao mesmo tempo) | 300 chamadas em ~1 min ≈ 300 RPM agregado | 3 RPM | ❌ rate-limit bloqueia 99% das chamadas — fallback manual ativaria para a maioria. **Cenário não previsto para a onda 1.** |

**Conclusão para a WAVE-2026-01:** **3 RPM cobre o volume real esperado com folga.** Cobre o **pico hipotético no limite exato** — sem margem para retentativas. A UX está protegida pelo fallback transparente da NRF §3.1 (STORY-015 CA-4) — em rate-limit estourado, o cadastro completa em modo manual com aviso amarelo, sem 500.

## Recomendação se a operação contratar plano pago

Os defaults conservadores (3 RPM) foram fixados pelo plano gratuito público. Quando a operação (EBC Parcerias) contratar plano pago:

| Cenário operacional | RPM recomendado por provider | Justificativa |
|---|---|---|
| Plano pago básico (sandbox de evolução) | **15 RPM** | Cobre pico hipotético com 5× de margem para retentativa e tráfego inesperado. |
| Plano comercial (pós-WAVE-2026-01, multi-setor + canal contábil) | **60 RPM** | Cobre o teto da NRF §1.3 (300 concorrentes) com fator de pico de 5 cadastros/min sustentado. |
| Pós-MVP (WAVE-2026-03+) com hotsite aberto e parceiros B2B2C | **revisar conforme volume** | Reabrir IDR-004 §"Defaults iniciais" — possivelmente diferenciar RPM por janela (minuto, hora, dia). |

**Aplicação operacional:** mudar via `inventories/<ambiente>/group_vars/vault.yml`:

```yaml
rfb_cnpja_rpm: 15           # antes: 3
rfb_receitaws_rpm: 15       # antes: 3
```

E rodar `ansible-playbook ... --tags env` + restart `web`/`worker` (mesmo procedimento do runbook `RFB-provider-switch.md` §2-§3).

## Sinais de revisão deste plano

Reabrir este documento quando **qualquer** ocorrer:

1. **Taxa de `erro_5xx` por rate-limit** (`business_metrics` com `meta->>'status' = 'erro_5xx'` precedido de `erro` interno de `RateLimiter`) > 1% das consultas RFB em janela de 24h sustentado.
2. **Volume real da onda** ultrapassar a estimativa em > 3× (ex.: > 300 cadastros em 12 semanas).
3. **Contratação de plano pago** em qualquer dos provedores (aplica recomendação da tabela acima).
4. **Mudança de escopo da onda** que abra hotsite público ou multi-setor (deixa de ser beta fechado).

## Referências

- PDR-001 — escopo da WAVE-2026-01 (beta fechado por convite).
- NRF §1.3 — capacidade concorrente (300 MVP, 1500 escalável).
- NRF §3.1 — fallback transparente em falha do provedor.
- IDR-004 — defaults iniciais de RPM.
- IDR-005 — primário `cnpja`.
- ADR-004 §1.5 — alerta A2 (taxa de erro > 5% / 10min, por provider).
