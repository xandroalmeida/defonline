---
story_id: STORY-018
slug: rfb-providers-reais-cnpja-receitaws
title: Ativação dos provedores reais da RFB (cnpja, receitaws) com rate-limit por provedor
epic_id: EPIC-001
sprint_id: SPRINT-2026-W22
type: implementation
target_role: programador
status: ready
owner_agent: null
created_at: 2026-05-23
updated_at: 2026-05-23
estimated_session_size: M
---

# STORY-018 — Provedores reais da RFB: `cnpja` e `receitaws` com rate-limit por provedor

> **Para o agente programador:** o **IDR-004** (2026-05-23) decidiu que a abstração `RfbCnpjClient` (entregue pela STORY-015) suporta **dois provedores reais selecionáveis via configuração**: `cnpja` (https://cnpja.com/) e `receitaws` (https://receitaws.com.br/). Esta estória entrega os dois clientes concretos, o bind condicional, o rate-limit por provedor e testes de integração contratuais. Nenhuma mudança de UX é introduzida — fluxo e fallback são exatamente os da STORY-015.

## Contexto (por que esta estória existe)

A STORY-015 deixou o sistema rodando contra `MockRfbCnpjClient` em `local`/`testing` e (necessariamente) também em `homologação` e `production`, porque o provedor real estava `[A DEFINIR]` na NRF §3.1. A IDR-004 fechou essa pendência: ao invés de eleger **um** provedor, registramos suporte a **dois** via abstração — para não amarrar o produto a um fornecedor único de uma dependência crítica (R5 do parecer CLAUDE) e para permitir A/B observacional de custo×qualidade em homologação.

Esta estória entrega o caminho real, mantendo a UX inalterada e respeitando os limites de chamadas de cada provedor (rate-limit por provedor — exigência explícita da IDR-004 e da NRF §3.1).

- Épico: `epics/EPIC-001-cadastro-minimo/epic.md`
- Documentos canônicos:
  - **IDR-004** — decisão da abstração e do schema de configuração (canônica para esta estória).
  - NRF §3.1 — RFB com dois provedores reais via abstração + rate-limit por provedor.
  - Espec funcional §3.3 Passo 2 — UX do pré-preenchimento (inalterada).
  - STORY-015 — base técnica (interface `RfbCnpjClient`, DTO, mock, métricas, alerta) sobre a qual esta estória se apoia.

## O quê (objetivo desta estória)

Implementar `App\Services\Rfb\CnpjaRfbCnpjClient` e `App\Services\Rfb\ReceitawsRfbCnpjClient` cumprindo o contrato `RfbCnpjClient` da STORY-015, com bind condicional no `AppServiceProvider` baseado em `config('services.rfb.provider')` e respeito ao rate-limit de cada provedor via `Illuminate\Support\Facades\RateLimiter`. Toda a UX, métricas, cache e fallback continuam exatamente como entregues pela STORY-015 — esta estória **não introduz mudança visível para o usuário**, apenas troca o componente que cumpre a interface.

## Por quê (valor para o usuário)

Para Roberto, é o salto **do mock para a fonte real** — o pré-preenchimento passa a refletir dados atuais da RFB e não dados sintéticos. Para o produto, fixa as duas opções de fornecedor (sem lock-in) e instrumenta o rate-limit que protege o custo da operação.

## Critérios de aceite

- [ ] **CA-1:** `config/services.php` → bloco `rfb` reflete exatamente o schema da IDR-004 (campos `provider`, `timeout`, `cache_ttl`, sub-bloco `providers.cnpja` e `providers.receitaws` com `base_url`, `api_key`, `rate_limit_per_minute`). `.env.example` ganha as variáveis correspondentes (`RFB_PROVIDER`, `RFB_TIMEOUT`, `RFB_CACHE_TTL`, `RFB_CNPJA_BASE_URL`, `RFB_CNPJA_API_KEY`, `RFB_CNPJA_RPM`, `RFB_RECEITAWS_BASE_URL`, `RFB_RECEITAWS_API_KEY`, `RFB_RECEITAWS_RPM`).
- [ ] **CA-2:** `App\Services\Rfb\CnpjaRfbCnpjClient` implementa `RfbCnpjClient`. Chama `GET {base_url}/office/{cnpj}` (ou o endpoint público canônico documentado em https://cnpja.com/ — o agente confirma o caminho exato e fixa nos testes). Headers de autenticação só são enviados se `api_key` estiver configurada (plano gratuito público dispensa). Resposta é mapeada para `RfbCnpjResult` (campos da STORY-015). Códigos de erro tratados: 404 → `cnpj_inexistente`; 429 → `erro_5xx` (rate-limit do provedor, tratado como falha externa); 5xx → `erro_5xx`; timeout (`RFB_TIMEOUT`) → `timeout`; erro de rede → `erro_rede`.
- [ ] **CA-3:** `App\Services\Rfb\ReceitawsRfbCnpjClient` implementa `RfbCnpjClient`. Chama `GET {base_url}/cnpj/{cnpj}` (endpoint público canônico documentado em https://receitaws.com.br/). Headers de autenticação só são enviados se `api_key` configurada. Mesmo mapeamento de erros que o CA-2. Atenção: o campo `status` da resposta do receitaws (`"OK" | "ERROR"`) deve ser checado antes de mapear os campos — `ERROR` com mensagem "CNPJ inválido" ou "Quota excedida" precisa ser distinguido (`cnpj_inexistente` vs. `erro_5xx`).
- [ ] **CA-4:** `AppServiceProvider` faz bind condicional de `RfbCnpjClient::class` baseado em `config('services.rfb.provider')`:
  - `'mock'` → `MockRfbCnpjClient` (STORY-015)
  - `'cnpja'` → `CnpjaRfbCnpjClient`
  - `'receitaws'` → `ReceitawsRfbCnpjClient`
  - valor desconhecido → exception clara em boot (`InvalidArgumentException` com nome do provedor recebido).
- [ ] **CA-5:** **Rate-limit por provedor.** Cada chamada feita por `CnpjaRfbCnpjClient` ou `ReceitawsRfbCnpjClient` passa por `Illuminate\Support\Facades\RateLimiter::attempt('rfb:provider:{provider}', $rpm, $callback, 60)` (ou equivalente em `tooManyAttempts` + `hit` + `availableIn`). RPM é lido de `config('services.rfb.providers.{provider}.rate_limit_per_minute')`. Se o slot não estiver disponível dentro do `timeout` configurado, a chamada falha com status `'erro_5xx'` (consistente com o mapeamento dos demais erros externos) — fallback transparente da STORY-015 cobre o impacto. O `MockRfbCnpjClient` segue ignorando o rate-limit (sem custo de operação para limitar).
- [ ] **CA-6:** Métricas em `business_metrics` ganham dimensão `provider` (campo já existente da tabela chave-valor — basta enriquecer o payload do registro existente da STORY-015). Cada `rfb_consulta` registra `provider: 'mock' | 'cnpja' | 'receitaws'` além de `status` e latência. **Alerta de >5% erro em 10 min é agora por provedor** (`MonitorarRfbErrorRate` agrupa por provider antes de aplicar a regra). Sem CNPJ em log/métrica (PII).
- [ ] **CA-7:** Cache (`rfb:cnpj:{sha256(cnpj)}` da STORY-015) é **respeitado** pelos clientes reais — antes da chamada HTTP, consulta cache; após sucesso, popula cache com `cache_ttl`. Erros NÃO são cacheados (próxima tentativa deve reconsultar o provedor). Isso evita custo desnecessário no provedor real.
- [ ] **CA-8:** Testes:
  - **UnitPure:** mapeamento de resposta para `RfbCnpjResult` (casos sucesso, `cnpj_inexistente`, `erro_5xx`, `timeout`, `erro_rede`) para cada um dos dois clientes — total 10 cenários no mínimo. Usar `Http::fake()` com fixtures gravadas em `tests/Fixtures/Rfb/{cnpja,receitaws}/`.
  - **Feature:** `AppServiceProvider` resolve o cliente correto para cada valor de `config('services.rfb.provider')`; rate-limit estoura quando RPM é excedido em janela de 60s (usar `RateLimiter::clear()` no `setUp`); cache hit não dispara HTTP.
  - **Integração contratual (opcional, marcada `@group external`):** chamada real contra cada provedor usando um CNPJ público sabidamente válido (`00.000.000/0001-91` — Banco do Brasil) — só roda manualmente, não no pre-push nem no CI default; serve como teste de contrato em mudanças de schema do provedor.
  - **Dusk:** o teste do fluxo da STORY-015 deve passar **sem nenhuma alteração** quando `RFB_PROVIDER=mock` em `testing` — esta estória não muda UX, só a fonte.
  - Cobertura: ≥ 80% geral; ≥ 98% se a lógica de mapeamento for isolada em `app/Domain/Rfb` (recomendado).

## Fora de escopo

- **Failover automático entre `cnpja` e `receitaws`** — IDR-004 mantém o fallback transparente para preenchimento manual (NRF §3.1). Failover entre provedores exige PDR (mudança de UX implícita e custo dobrado em chamadas) — fora do MVP.
- **Plano contratado em cada provedor.** Operação (EBC Parcerias). Esta estória entrega o caminho técnico funcionando com plano gratuito público (3 RPM default).
- **Qual dos dois provedores será o primário em produção.** PO já confirmou (2026-05-23) que **produção usará os dois mesmos provedores** decididos no IDR-004; o que falta é a definição operacional do `RFB_PROVIDER` default em `production`. Decisão do Arquiteto, a ser registrada como IDR separado quando a STORY-018 entregar e houver dados de A/B em homologação. STORY-018 entrega ambos os clientes prontos e configuráveis — sem mudança de código quando o IDR de default for fechado.
- **Painel administrativo para trocar provedor sem deploy.** Fora do MVP — troca via `.env` + restart é suficiente.
- **Webhooks de atualização da RFB** — fora do MVP (já estava fora da STORY-015).

## Padrões de qualidade exigidos

- Cobertura ≥ 80% geral; ≥ 98% em `app/Domain/Rfb` se a lógica de mapeamento for isolada lá.
- **CNPJ é PII** — nunca em log/evento sem mascarar (mantido da STORY-015).
- **Timeout curto** (≤ 5s) — herdado da STORY-015. O rate-limit consome parte deste budget — em rate-limit estourado, falhar rápido, não esperar 60s.
- **Fallback transparente** — herdado da STORY-015. Esta estória NÃO pode degradar a UX em caso de falha do provedor real.
- Sem chamadas externas em testes do pre-push/CI default (`@group external` separa testes contratuais).

## Dependências

- **Bloqueada por:** STORY-015 (interface, DTO, mock, cache, métricas, alerta — tudo herdado).
- **Bloqueia:** nenhuma estória no EPIC-001 (validação STORY-017 não exige provedor real — IDR-004 mantém mock como default em homologação até decisão explícita).
- **Pré-requisitos de ambiente:** mesmos do EPIC-000 + EPIC-001. Para `@group external`, conectividade com `api.cnpja.com` e `receitaws.com.br` a partir do ambiente onde o teste rodar.

## Decisões já tomadas

- **Provedores suportados:** `cnpja` e `receitaws` (IDR-004).
- **Seleção via `config/services.php → rfb.provider`** (IDR-004).
- **Rate-limit por provedor configurável via `rfb.providers.<provider>.rate_limit_per_minute`** (IDR-004).
- **Produção usará os dois mesmos provedores** (`cnpja` + `receitaws`) — confirmado pelo PO em 2026-05-23. Qual dos dois será o primário (`RFB_PROVIDER` default em produção) é decisão do Arquiteto, a ser registrada em IDR separado quando esta estória entregar.
- **Fallback transparente para preenchimento manual em qualquer falha** — não failover automático entre provedores (NRF §3.1).

## Liberdade técnica do agente

Você decide:
- Endpoint exato de cada provedor (confirmar contra a documentação oficial pública).
- Como organizar fixtures de `Http::fake()` (sugestão: arquivo `.json` por cenário em `tests/Fixtures/Rfb/{provider}/`).
- Se a lógica de mapeamento provedor-específica fica num `Mapper` separado ou no próprio cliente.
- Implementação exata do RateLimiter (sugestão: `attempt` blocante curto com `timeout` configurável, ou checagem prévia + fail-fast).

Você **não** decide:
- Que tem dois provedores suportados (IDR-004).
- Schema do `config/services.php → rfb` (IDR-004).
- Que rate-limit é por provedor (IDR-004).
- Que default em produção é `mock` até decisão explícita (IDR-004).

## Definição de Pronto (DoD)

- [ ] CAs passam.
- [ ] Pre-push verde (sem testes `@group external`).
- [ ] Pipeline CI verde.
- [ ] Deploy em homologação validado:
  - `RFB_PROVIDER=cnpja` + CNPJ real público → pré-preenche com dados reais; métrica `rfb_consulta` com `provider: cnpja, status: sucesso`.
  - `RFB_PROVIDER=receitaws` + CNPJ real público → idem com `provider: receitaws`.
  - `RFB_CNPJA_RPM=1` + duas chamadas em < 60s → segunda falha com `erro_5xx` e UX mostra fallback amarelo da STORY-015 (sem stack trace, sem 500).
  - `RFB_PROVIDER=mock` → fluxo da STORY-015 inalterado.
- [ ] `index.json` `done`.
- [ ] "Notas do agente" preenchidas incluindo: endpoints reais usados, fixtures gravadas, RPM testado.

## Protocolo do agente (obrigatório)

Padrão `agent-task-format.md`.

## Notas do agente

### Decisões tomadas
- <data> — <decisão>

### Descobertas
- <data> — <gotcha>

### Bloqueios encontrados
- <data> — <bloqueio>

### IDRs criados
- IDR-XXX — <título>

### Endpoints confirmados
- cnpja: <endpoint>
- receitaws: <endpoint>

### Cobertura final
- Geral: <%>
- `app/Domain/Rfb` (se isolado): <%>

### Links de evidência
- PR: <url>
- Pipeline: <url>
- Tag rc.N: <vX.Y.Z-rc.N>
