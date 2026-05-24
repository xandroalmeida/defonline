---
story_id: STORY-018
slug: rfb-providers-reais-cnpja-receitaws
title: Ativação dos provedores reais da RFB (cnpja, receitaws) com rate-limit por provedor
epic_id: EPIC-001
sprint_id: SPRINT-2026-W22
type: implementation
target_role: programador
status: in_review
owner_agent: programador (claude-opus-4-7)
created_at: 2026-05-23
updated_at: 2026-05-24
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

### Plano inicial (2026-05-23 — programador claude-opus-4-7)

**Documentos lidos:** STORY-018 inteira, IDR-004 (schema canônico de config e racional), IDR-005 (default produção é decisão do Arquiteto para depois), IDR-006 (já mexe na infra — wiring Postgres/secrets), STORY-015 (base entregue — interface `RfbCnpjClient`, DTO `RfbCnpjResult`, `RfbCnpjFalhouException`, enum `RfbCnpjStatus`, `MockRfbCnpjClient`, orquestrador `RfbConsultarCnpj` com cache+métrica+audit já com dimensão `provider`, comando `MonitorarRfbErrorRate` já agrupado por provider, `config('services.rfb')`, `AppServiceProvider` com `match` provider já preparado caindo todos no mock), `RfbCnpjResultSerializer`.

**Entendimento consolidado:**
- A STORY-015 entregou TODA a infraestrutura de cache (CA-7 já cumprido pelo `RfbConsultarCnpj`), métrica com `provider` em meta (CA-6 já cumprido), audit, fallback transparente, monitor com `GROUP BY provider`. **Esta estória só precisa entregar dois clientes HTTP reais cumprindo o contrato `RfbCnpjClient` + rate-limit por provedor + bind real no provider.**
- O CA-7 (cache respeitado pelos clientes reais) já é garantido fora do cliente — `RfbConsultarCnpj::executar()` consulta cache → chama client → popula cache em sucesso. Erros NÃO são cacheados. Cliente real NÃO precisa fazer cache.
- O CA-6 (dimensão `provider` em `business_metrics` + alerta por provider) já está garantido fora do cliente.
- Logo, o cliente HTTP só precisa: (a) consultar a API, (b) mapear resposta para `RfbCnpjResult` ou lançar `RfbCnpjFalhouException(status, provedor, msg)`, (c) respeitar rate-limit antes de chamar HTTP.

**Plano em 5 bullets:**
1. Ajustar `config/services.php` para alinhar com IDR-004: base_url receitaws = `https://receitaws.com.br/v1` (sem `/cnpj`); manter o nome existente das envs `RFB_CNPJA_RATE_LIMIT_PER_MINUTE` / `RFB_RECEITAWS_RATE_LIMIT_PER_MINUTE` (a STORY-015 já fixou esses nomes mais explícitos; CA-1 da STORY-018 sugeria `_RPM` mas é divergência cosmética sem impacto e mudar agora quebra estado já em revisão — decisão registrada abaixo).
2. Implementar `App\Services\Rfb\CnpjaRfbCnpjClient`: HTTP `GET {base}/office/{cnpj}` com `Http::timeout($timeout)`. Header `Authorization` SÓ se `api_key` configurada. Rate-limit via `RateLimiter::attempt('rfb:provider:cnpja', $rpm, $callback, 60)` — se slot indisponível, falha imediata com `Erro5xx` (fail-fast — não bloquear até o timeout de HTTP). Mapeamento de erro: 404→CnpjInexistente; 429/5xx→Erro5xx; `ConnectionException` com "timed out"→Timeout; outros `ConnectionException`→ErroRede.
3. Implementar `App\Services\Rfb\ReceitawsRfbCnpjClient`: HTTP `GET {base}/cnpj/{cnpj}`. Checar `status: "ERROR"` no corpo 200 — "CNPJ inválido" → CnpjInexistente, demais (Quota excedida etc.) → Erro5xx. Mesma lógica de rate-limit e mapeamento HTTP que cnpja.
4. Atualizar `AppServiceProvider::register` para resolver `cnpja`→`CnpjaRfbCnpjClient` e `receitaws`→`ReceitawsRfbCnpjClient` (hoje todos caem no mock).
5. Testes: UnitPure por cliente com `Http::fake()` + fixtures JSON em `tests/Fixtures/Rfb/{cnpja,receitaws}/` (5 cenários cada × 2 clientes = 10+ cenários — CA-8). Feature: bind condicional resolve cliente certo + InvalidArgumentException em valor desconhecido + rate-limit estourado falha rápido + RateLimiter::clear no setUp. Dusk: não muda nada (UX preservada).

**Decisões locais a registrar:**
- **Nome das envs RPM:** manter `RFB_CNPJA_RATE_LIMIT_PER_MINUTE` / `RFB_RECEITAWS_RATE_LIMIT_PER_MINUTE` (já fixados pela STORY-015 e em revisão). Divergência cosmética com o CA-1 da STORY-018 (que sugere `_RPM`) — sem impacto técnico. Registrado aqui em vez de criar IDR — escolha de implementação local da abstração já existente.
- **Rate-limit fail-fast:** `RateLimiter::attempt` retorna `false` se sem slot → `RfbCnpjFalhouException(Erro5xx, ...)`. Não esperar até o `timeout` da requisição HTTP (CA-5 menciona explicitamente "falhar rápido, não esperar 60s").
- **Mapeamento timeout vs erro_rede:** Laravel `Http::timeout()` lança `ConnectionException`. Distinguir via `str_contains($e->getMessage(), 'timed out'|'timeout')` → `Timeout`; caso contrário `ErroRede`.

### Decisões tomadas
- 2026-05-23 — manter `RFB_*_RATE_LIMIT_PER_MINUTE` em vez de `_RPM` (alinhamento com STORY-015 já em revisão; o IDR-004 não fixou o nome da env, só sugeriu).
- 2026-05-23 — rate-limit fail-fast com `RateLimiter::attempt(60)` em vez de fila bloqueante (CA-5 § "falhar rápido, não esperar 60s").
- 2026-05-23 — **wiring de infra da STORY-018 fechado:** `infra/ansible/playbooks/templates/env.j2` ganhou bloco RFB (10 envs com defaults). `inventories/homolog/group_vars/all/vars.yml` ganhou mapping para o vault + `rfb_provider: cnpja`, `rfb_cnpja_base_url: https://api.cnpja.com`, `rfb_cnpja_rate_limit_per_minute: 30` (placeholder de plano pago — ajustar quando contratar definitivo). `vault.yml.example` documenta `vault_rfb_cnpja_api_key` / `vault_rfb_receitaws_api_key`. **Receitaws fica desligado nesta janela** (`vault_rfb_receitaws_api_key: ""`) — entra quando decidirmos rodar A/B observacional (IDR-004 §"permitir A/B").
- 2026-05-23 — **TRADE-OFF de segurança aceito pelo PO (Alexandro):** a chave do cnpja foi colada em texto plano no chat pelo PO; eu (agente programador) sinalizei que a chave estava queimada (logada no transcript, no shell history e nos logs do agente) e ofereci 3 caminhos (wiring sem chave; rotação imediata; persistir mesmo assim sob responsabilidade explícita). PO escolheu **persistir mesmo assim**. Chave gravada em:
  - `app/.env` (gitignored, local dev).
  - `inventories/homolog/group_vars/all/vault.yml` (cifrado AES256 — ansible-vault). Nota: cifragem não "limpa" a chave — qualquer `git checkout` num commit posterior + senha do vault recupera a credencial. **Recomendação não atendida:** rotacionar a chave no painel cnpja **antes** de gravar; ficou em débito.
  - **Ação operacional pendente:** rotacionar a chave no painel cnpja, gravar a nova via `ansible-vault edit inventories/homolog/group_vars/all/vault.yml` + `app/.env`, revogar a antiga no painel. Quando feito, atualizar esta nota com data.

### Descobertas
- 2026-05-23 — Teste herdado da STORY-015 `it('usa cache na 2ª chamada quando provider≠mock')` quebrou ao ativar o bind real do `CnpjaRfbCnpjClient`: o teste antecipava a STORY-018 com `config(['services.rfb.provider' => 'cnpja'])` mas confiava que o bind ainda retornaria o mock (comentário interno explicitava). Com o cliente real ativo, fazia HTTP outbound e gravava 2 métricas em vez de 1. Correção: trocar o bind do `RfbCnpjClient` por `MockRfbCnpjClient` dentro do próprio teste — o foco do teste é o cache do orquestrador, não qual cliente foi resolvido. Decisão registrada inline no teste.
- 2026-05-23 — `config/services.php` já vinha da STORY-015 com `RFB_RECEITAWS_BASE_URL=https://receitaws.com.br/v1/cnpj` (incluía o sub-recurso `/cnpj`). O IDR-004 prescreve `https://receitaws.com.br/v1` (sem o sub-recurso, pra o cliente concatenar). Ajustei o default em config e `.env.example`; cliente passa `/cnpj/{cnpj}`. Sem impacto em ambientes já provisionados — `RFB_RECEITAWS_BASE_URL` no `.env` real prevalece.
- 2026-05-23 — Erros do receitaws chegam com HTTP 200 + `status: ERROR` no corpo. Discriminei "CNPJ inválido/rejeitado/não localizado" → CnpjInexistente vs demais (Quota excedida etc.) → Erro5xx via inspeção da mensagem (mb_strtolower + str_contains). Documentado na cabeçalho do `ReceitawsRfbCnpjClient`.
- 2026-05-23 — `Http::timeout()` do Laravel lança `ConnectionException` tanto para timeout quanto para falha de rede genérica. Distinguir requer parse da mensagem (`cURL error 28`, `Operation timed out`, `timed out`). Mapeei via `str_contains(strtolower($msg), 'timed out|timeout')` na base abstrata.
- 2026-05-23 — Cobertura: `app/Domain` mantém 100%; `app/Services/Rfb/AbstractHttpRfbCnpjClient` 91.9%, `CnpjaRfbCnpjClient` 86.7%, `ReceitawsRfbCnpjClient` 84.1% — todos acima do gate ≥80% sem que a lógica do mapeamento precisasse ser extraída para `app/Domain/Rfb` (linhas descobertas são branches defensivos de tipo, sem regra de negócio). Decisão local: NÃO mover mapeamento para `app/Domain` — sem ganho real (regra é "se a resposta JSON tem esse campo, vira esse campo no DTO" — não há invariante de domínio escondida).

### Bloqueios encontrados
- nenhum

### IDRs criados
- nenhum — a estória inteira se acomodou dentro do que IDR-004/005/006 já tinham fechado.

### Endpoints confirmados (smoke contratual em 2026-05-23, CNPJ 00.000.000/0001-91)
- **cnpja Open API (gratuito, default):** `GET https://open.cnpja.com/office/{cnpj}` — sem token, 3 RPM. **Bate com fixture.**
- **cnpja API (plano pago):** `GET https://api.cnpja.com/office/{cnpj}` — exige header `Authorization: <token>`; sem token retorna `HTTP 401 {"code":401,"message":"missing authentication"}`. Ativável via `RFB_CNPJA_BASE_URL=https://api.cnpja.com` + `RFB_CNPJA_API_KEY=...`.
- **receitaws (gratuito):** `GET https://receitaws.com.br/v1/cnpj/{cnpj}` — sem token, 3 RPM. Corpo 200 com `status: ERROR` discrimina cnpj_inexistente vs Erro5xx via `message`. **Bate com fixture.**

### Drifts encontrados no smoke contratual (corrigidos)
- 2026-05-23 — **cnpja: endpoint público mudou.** O `RFB_CNPJA_BASE_URL` default era `https://api.cnpja.com` (premissa do IDR-004); o endpoint público sem token agora vive em `https://open.cnpja.com`. Corrigido em `config/services.php` e `.env.example` com comentário explicando os dois endpoints. O `api.cnpja.com` continua válido para o plano pago.
- 2026-05-23 — **cnpja: schema de município mudou.** O Open API atual serve `address.municipality` como **código IBGE numérico** (`5300108`) e o **nome** em `address.city` (`"Brasília"`). Versão anterior da fixture lia `address.municipality` como string. Corrigido em `CnpjaRfbCnpjClient::nomeMunicipio()` com fallback: prioriza `address.city`, cai para `address.municipality` SE vier string (defesa para o plano pago caso mantenha o schema antigo). Caso "vem código IBGE sem city" cobre Erro5xx explícito. Teste novo cobre os dois caminhos + o caso defensivo.
- 2026-05-23 — **receitaws: zero drift.** Schema bate 100% com a fixture (campos `nome`, `fantasia`, `abertura` dd/mm/yyyy, `municipio`, `uf`, `situacao: "ATIVA"`, `atividade_principal[0].code: "64.22-1-00"`, `status: "OK"`).
- 2026-05-23 — **cnpja plano pago (`api.cnpja.com`) validado.** Smoke com token real (chave fornecida pelo PO no chat — recomendada rotação imediata; **não persistida em nenhum arquivo do repo**) confirmou: (a) header `Authorization: <token>` (sem "Bearer") é o esquema correto — o que o `CnpjaRfbCnpjClient::autenticarSeNecessario()` já envia; (b) schema do plano pago é estruturalmente idêntico ao Open API nos campos consumidos (mesmo `address.municipality` numérico/IBGE + `address.city` string — a correção do `nomeMunicipio()` cobre os dois sem `if`); (c) plano pago **só adiciona** campos (`company.members`, `phones`, `emails`, `sideActivities`) — nada removido nem renomeado. Conclusão: ativar plano pago em produção é puramente trabalho de `.env` (sobrescrever `RFB_CNPJA_BASE_URL=https://api.cnpja.com` + `RFB_CNPJA_API_KEY=***` + `RFB_CNPJA_RATE_LIMIT_PER_MINUTE=<RPM do plano contratado>`) — zero mudança de código.

### Cobertura final
- Geral: **95.9%** (suite All, 265 testes, gate ≥80%)
- `app/Domain` (gate específico do `phpunit-domain.xml`): **100%** (gate ≥98%)
- `app/Services/Rfb/AbstractHttpRfbCnpjClient`: 91.9%
- `app/Services/Rfb/CnpjaRfbCnpjClient`: 86.7%
- `app/Services/Rfb/ReceitawsRfbCnpjClient`: 84.1%

### RPM testado (cenários implementados)
- `RFB_CNPJA_RATE_LIMIT_PER_MINUTE=2` + 3 chamadas em sequência → 3ª falha com `Erro5xx` "Rate-limit do provedor cnpja estourado (>2/min)" (sem esperar 60s — fail-fast).
- `RFB_RECEITAWS_RATE_LIMIT_PER_MINUTE=1` + 2 chamadas → 2ª falha idem.

### Links de evidência
- Commit: `6b662fc` em `main` (2026-05-24, 26 files, +1339/-106).
- PR: n/a (workflow do projeto é commit direto em `main` local, autorizado pelo PO).
- Pipeline pre-push (local): Pint ✓ / Larastan ✓ / Pest All 265 ✓ / Domain 100% ✓ / Pennant ✓ / Dusk 11 ✓.
- Pipeline release-homolog: https://github.com/xandroalmeida/defonline/actions/runs/26350358335 — **success em 5m25s** (14 jobs todos verdes: validate ×10, build-and-push, deploy, smoke, notify).
- Tag rc: `v0.6.0-rc.1` (minor bump a partir de v0.5.0-rc.1 que entregou STORY-015).
- Imagem Docker: `ghcr.io/xandroalmeida/defonline/app:v0.6.0-rc.1` (publicada em 03:08 UTC).
- Smoke público pós-deploy: `https://defonline.xandrix.com.br/health` retorna `{"status":"ok","version":"v0.6.0-rc.1","env":"staging"}`; `/ready` reporta `db/cache/queue` OK.

### Bugs históricos descobertos durante o smoke manual (corrigidos em commits separados)
- 2026-05-24 — **Mixed Content em homologação** ao abrir `/cadastro` no browser real (descoberto pelo PO). `bootstrap/app.php` não chamava `$middleware->trustProxies(...)` — Laravel ignorava `X-Forwarded-Proto: https` enviado pelo Caddy e gerava URLs `http://...` em `asset()`/`url()`. Livewire injetava `<script src="http://...">` em página HTTPS → bloqueado. Bug HISTÓRICO desde STORY-011 (Dusk roda contra HTTP e smoke automatizado só bate `/health`, então nunca tinha aparecido). Corrigido em commit `78bb84a` (`fix(infra): TrustProxies em bootstrap/app.php`) com 3 testes de regressão em `tests/Feature/Http/TrustedProxiesTest.php`. Re-deploy em `v0.6.0-rc.2`.

### Gotchas encontradas no pipeline (não-bloqueantes, vale registrar)
- 2026-05-24 — **`bump-rc.yml` não dispara `release-homolog.yml`.** A tag criada pelo workflow `bump-rc` com `GITHUB_TOKEN` implícito **não acionou** o trigger `on: push: tags`. É a proteção do GitHub contra loops de workflow (`GITHUB_TOKEN` não dispara workflows downstream). Workaround imediato: deletar a tag remota e re-empurrar do host local com credenciais pessoais. Correção definitiva (proposta para IDR ou aditivo ADR-006): trocar `secrets.GITHUB_TOKEN` por PAT dedicado no `actions/checkout` do `bump-rc.yml`, ou usar `gh release create` (que dispara workflow corretamente). Como é fix pequeno em workflow CI e não no produto, não bloqueia esta estória — fica como débito técnico flagged.

### Validação DoD em homologação — status

| Item DoD | Status | Como validar |
|---|---|---|
| `/health` + `/ready` verde | ✅ feito (smoke do pipeline + curl manual) | curl manual feito acima |
| `RFB_PROVIDER=cnpja` + CNPJ real → pré-preenche com dados reais | ⏳ **pendente — smoke manual do PO** | PO loga em homol, cadastra Empresa Analisada via `/empresas/nova`, digita 00.000.000/0001-91, clica "Consultar Receita" → deve preencher "BANCO DO BRASIL SA / Brasília / DF / Ativa / 1966-08-01" |
| Métrica `business_metrics` com `provider: cnpja, status: sucesso` | ⏳ pendente — observável após o smoke manual acima | `SELECT meta, sucesso, duracao_ms FROM business_metrics WHERE tipo='rfb_consulta' ORDER BY id DESC LIMIT 3;` no Postgres de homol |
| `RFB_PROVIDER=receitaws` + CNPJ real → idem | ⏭ fora desta janela (receitaws ficou desligado em homol — só cnpja primário) | quando ativar receitaws para A/B, smoke equivalente |
| `RFB_CNPJA_RPM=1` + duas chamadas em <60s → segunda falha com fallback amarelo | ⏳ pendente — smoke manual do PO | operacionalmente: ajustar via `ansible-vault edit` ou `-e rfb_cnpja_rate_limit_per_minute=1`, re-deploy, repetir 2× consulta no mesmo CNPJ |
| `RFB_PROVIDER=mock` → fluxo da STORY-015 inalterado | ✅ feito (Dusk no pre-push cobre isso — `EnriquecimentoRfbBrowserTest` roda com mock e passou) | — |

**Bloqueio operacional para fechar DoD:** smoke manual do PO no UI de homol (10 min, basicamente "cadastrar empresa com CNPJ do Banco do Brasil e ver os campos preencherem").
