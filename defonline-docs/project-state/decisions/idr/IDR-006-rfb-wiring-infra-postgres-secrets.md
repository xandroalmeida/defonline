---
idr_id: IDR-006
slug: rfb-wiring-infra-postgres-secrets
title: Wiring de infra da abstração RFB — cache e RateLimiter no driver `database` (Postgres), secrets via Ansible Vault (mecanismo de ADR-005 §7.7)
status: accepted
decided_at: 2026-05-23
decided_by: Arquiteto
approved_by: Alexandro
owner_agent: Arquiteto
related_story: STORY-018
related_idrs: ["IDR-004", "IDR-005"]
related_adrs: ["ADR-002", "ADR-003", "ADR-005"]
supersedes: null
superseded_by: null
created_at: 2026-05-23
updated_at: 2026-05-23
revision_history:
  - at: 2026-05-23
    by: Arquiteto
    note: "Versão provisória criada pelo PO em accepted (decided_by: Arquiteto provisório, sem método aplicado) — reaberta pelo Arquiteto, concretizada a parte de secrets (era vaga: \"mesmo mecanismo\"), validados os princípios contra ADR-002/003/005. Status revertido para proposed."
  - at: 2026-05-23
    by: Alexandro
    note: "Aprovação humana explícita no chat (\"pode aprovar tudo\"). Status promovido para accepted."
---

# IDR-006 — Wiring de infra da abstração RFB: cache e RateLimiter via `database` (Postgres); secrets via Ansible Vault

## Contexto

A IDR-004 introduziu a abstração `RfbCnpjClient` com dois provedores reais e rate-limit por provedor. Três pontas de infra ficaram em aberto para o Arquiteto:

1. **Onde fica o `RateLimiter`** (chave `rfb:provider:{provider}`) — `Illuminate\Support\Facades\RateLimiter` usa internamente o driver de `cache.default`. Em monolito com `web` + `worker` rodando em paralelo (ADR-002), a contagem precisa ser **global**, não in-process.
2. **Onde fica o cache** das respostas bem-sucedidas (chave `rfb:cnpj:{sha256(cnpj)}`, TTL parametrizado por `rfb.cache_ttl`, default 300s) — STORY-015 CA-6 deixou o driver em aberto.
3. **Como ficam as secrets** `RFB_CNPJA_API_KEY` e `RFB_RECEITAWS_API_KEY` — ADR-005 §7.7 documenta o mecanismo geral; este IDR concretiza para essas duas chaves.

Os três pontos são wiring operacional sobre infra já decidida (ADR-002 §princípio Postgres-first; ADR-003 §"Tabelas técnicas" reafirma `cache` no PG; ADR-005 §2.1+§7.7 fixa Ansible + Vault). Por isso IDR, não ADR — não há decisão estrutural nova.

## Forças (drivers) da decisão

- **F1 — Princípio #3 (Postgres-first)** — **Alto**. Adicionar Redis para RateLimiter/cache exige números que o volume MVP não dá (ver F5).
- **F2 — Contagem global entre `web` e `worker` (ADR-002)** — **Alto**. RateLimiter precisa ver ambos os processos. Driver `array` ou `file` quebra.
- **F3 — Sem dependência operacional nova (princípio #1 + #11)** — **Alto**. Tabela `cache` já existe (ADR-003 §"Tabelas técnicas"); aproveitar é zero-cost.
- **F4 — Reversibilidade (princípio #7)** — **Alto**. Trocar de `database` para `redis` no futuro é mudar `CACHE_STORE` no `.env` — sem mudança de código.
- **F5 — Volume MVP** — **Médio**. WAVE-2026-01 é beta fechado por convite (PDR-001). Estimativa: ≤ centenas de cadastros no horizonte da onda; ≤ alguns RPM no pior caso. Postgres + `database` driver com `SKIP LOCKED` (já adotado para queue em ADR-002) é folgado.
- **F6 — Secrets via mecanismo já estabelecido (ADR-005 §7.7)** — **Alto**. Não inventar nada novo — `Ansible Vault → render Jinja → .env chmod 600 no host`.

## Decisão proposta

### 1. `RateLimiter` (`rfb:provider:{provider}`): driver `database` (Postgres)

`Illuminate\Support\Facades\RateLimiter` usa internamente o `cache.default`. Em `production` e `staging`, `CACHE_STORE=database` (já é o default Laravel 12+ quando o `.env` não define explicitamente — confirmar no `.env` renderizado pelo Ansible). Tabela `cache` no schema `public`, criada pela migration Laravel padrão (`php artisan cache:table` + `migrate`) — já estava prevista em ADR-003 §"Tabelas técnicas".

Contagem é global entre `web` e `worker` por construção (mesma tabela, mesmo registro).

### 2. Cache de respostas RFB (`rfb:cnpj:{sha256(cnpj)}`, TTL `rfb.cache_ttl`): driver `database` (Postgres)

Mesmo driver, mesma tabela `cache`. Apenas respostas com `status: sucesso` populam o cache (CA-7 da STORY-018). Erros NÃO são cacheados.

### 3. Secrets `RFB_CNPJA_API_KEY` e `RFB_RECEITAWS_API_KEY`: Ansible Vault, mesmo template de `.env`

Concretizando o "mesmo mecanismo já usado" do PO no chat:

| Camada | Onde mora | Como entra |
|---|---|---|
| **Repositório (cifrado)** | `infra/ansible/inventories/<ambiente>/group_vars/vault.yml` (Ansible Vault — ADR-005 §2.1) | `ansible-vault edit` adiciona as chaves `rfb_cnpja_api_key`, `rfb_receitaws_api_key`. |
| **Template Jinja** | template `.env.j2` (mesmo template que já renderiza `DB_PASSWORD`, `TELEGRAM_BOT_TOKEN`, etc — ADR-005 §7.7) | Linhas novas: `RFB_CNPJA_API_KEY={{ rfb_cnpja_api_key \| default('') }}` e equivalente para `receitaws`. |
| **Host (em runtime)** | `.env` em `chmod 600 root:root` (ADR-005 §7.7) | Gerado pelo playbook `app.yml` no provisionamento. |
| **CI/CD** | nenhuma chave — CI não usa provedor real (testes `@group external` rodam manualmente, STORY-018 CA-8) | Sem GitHub Secrets necessários para estas duas chaves. |

**Nenhuma alteração de mecanismo, nenhum novo serviço de gestão de secret.** O que muda em relação ao status atual:

- `.env.example` ganha `RFB_CNPJA_API_KEY=` e `RFB_RECEITAWS_API_KEY=` (vazios — local/testing usa `mock`).
- `inventories/homolog/group_vars/vault.yml` ganha as 2 chaves (vazias se plano gratuito; populadas se plano pago contratado).
- `inventories/producao/group_vars/vault.yml` idem.

## Justificativa

### Por quê `database` (Postgres) para cache e RateLimiter

- **Princípio #3 (Postgres-first), central.** Adicionar Redis para isso exigiria números que o volume MVP não dá. WAVE-2026-01 é beta fechado (PDR-001) com poucos cadastros — a tabela `cache` segura. Estimativa: ~5000 análises/mês × 1 consulta RFB cada × TTL 300s ≈ dezenas de linhas vivas simultaneamente, e a contagem do RateLimiter cabe em 2 linhas (`rfb:provider:cnpja` e `rfb:provider:receitaws`).
- **Contagem global por construção (F2).** `web` e `worker` rodam contra a mesma tabela `cache` — `database` é o único driver Laravel disponível no MVP que satisfaz isso sem adicionar serviço novo.
- **Sem dependência operacional adicional (F3).** Tabela já existe (ADR-003); nenhum novo container, healthcheck, monitor de memória, failover.
- **Reversível (F4).** Se o RateLimiter virar gargalo (improvável), trocar para Redis é mudar `CACHE_STORE=database` por `CACHE_STORE=redis` no `.env` — sem mudança de código.

### Por quê NÃO `file` ou `array`

- **`file`:** cada processo PHP-FPM tem seu próprio handle de filesystem; o `web` e o `worker` podem ler/escrever versões diferentes do counter. **Inadequado para RateLimiter** (perde contagem global).
- **`array`:** vive só durante o request — counter sempre zero entre requests. Inútil.

### Armadilha de contenção sob carga (trade-off honesto)

O driver `database` usa `UPDATE cache SET value = ... WHERE key = ...` para incrementar o counter, e o Laravel envolve isso em `DB::transaction` com `LOCK FOR UPDATE` implícito quando há atomic increment. Em **alta concorrência** (centenas de requests/segundo para o mesmo `rfb:provider:cnpja`), isso vira contenção de linha.

**No volume MVP (3 RPM por provedor × 2 provedores × poucos cadastros simultâneos)**, contenção é desprezível — o próprio rate-limit limita a frequência de acesso à linha. **Sinal de revisão** registrado abaixo: se a métrica `business_metrics` de `rfb_consulta` mostrar p95 do RateLimiter > 100ms sustentado, ou se a operação contratar plano com >100 RPM (rate-limit deixa de proteger contra contenção), reabrir.

### Por quê secrets via Ansible Vault sem mudança

ADR-005 §7.7 já documenta o mecanismo: gerador automático no first-boot, render Jinja para `.env`, chmod 600. As duas novas chaves seguem o mesmo template — não há decisão arquitetural nova a tomar. **Não é "vago"** — é reuso explícito de mecanismo documentado.

## Consequências

### Positivas (o que ganhamos)

- **STORY-018 destravada para deploy.** Programador sabe exatamente onde gravar a chave (`vault.yml`) e qual template Jinja renderiza.
- **Zero novo container, zero novo serviço.** Princípio #1 honrado.
- **Reversibilidade barata.** Quando/se Redis entrar no projeto por outro motivo (roadmap pós-MVP — `arquitetura-tecnica.md` §9.5), mover cache + RateLimiter para Redis é mudar `CACHE_STORE` no `.env` por ambiente.
- **`.env.example` ganha as duas variáveis vazias**, alinhado a IDR-004 §"Forma do bloco de configuração".

### Negativas / trade-offs aceitos

- **Contenção potencial de linha sob carga alta** — aceito, mitigado pelo próprio rate-limit (F4 acima). Sinal de revisão registrado.
- **Backup do `vault.yml` é o backup do segredo.** Quem tem acesso ao repo + senha do Vault tem a chave da RFB. Aceito pela mesma postura adotada para `DB_PASSWORD` e `TELEGRAM_BOT_TOKEN` em ADR-005 — está dentro do envelope de risco já assumido.
- **`config/cache.php`** em `production` precisa garantir `CACHE_STORE=database`. É o default Laravel 12+; basta confirmar no template `.env.j2` que não está sobrescrito. **Verificação:** teste arquitetural (Pest) que asserta `config('cache.default') === 'database'` no ambiente de homol/prod.

### Neutras

- **Limpeza da tabela `cache`:** `php artisan cache:prune-stale-tags` no scheduler (ADR-002 §scheduler) — diário às 04:00 BRT (sugestão; IDR do Programador refina horário).
- **Crescimento da tabela `cache`:** desprezível no envelope MVP (dezenas de linhas vivas simultaneamente).
- **Migrations da tabela `cache`:** verificar se já existe no schema de produção (provavelmente criada no EPIC-000 / STORY-007); STORY-018 garante via migração defensiva (`Schema::hasTable('cache')` + criação).

### Para o time

- **Impacto em estórias existentes:**
  - **STORY-018**: ganha resposta a "onde fica RateLimiter, cache, secrets" — CA-5, CA-7 destravados.
- **ADRs/IDRs relacionados:**
  - **Aditivo a ADR-005 §7.7** — não supersede; ADR continua válida, este IDR só adiciona duas chaves ao Vault.
  - **Aderente a ADR-002** §"App → RFB (externo)" — explicita o driver mencionado lá.
  - **Aderente a ADR-003** §"Tabelas técnicas" — reusa tabela `cache`.
- **Necessidade de spike de validação:** **não.** Mecanismos já validados pelo EPIC-000.

## Plano de verificação

- **Como verificar conformidade:**
  - **Teste arquitetural Pest** (sugestão para Programador): `Tests\Architectural\RfbCacheDriverIsDatabaseTest` — asserta em `staging`/`production` que `config('cache.default') === 'database'`.
  - **Teste de integração** (Programador): rate-limit estoura → segunda chamada retorna `erro_5xx` (já previsto na STORY-018 CA-5).
  - **Inspeção manual** pós-deploy: `SELECT key FROM cache WHERE key LIKE 'rfb:%' LIMIT 5;` no Postgres de homol mostra entradas reais.

- **Sinais de revisão (quando reabrir esta decisão):**
  1. Contagem da tabela `cache` vira gargalo de I/O (p99 de SELECT > 100ms sustentado) ou de tamanho (>100k linhas vivas).
  2. p95 do RateLimiter (mensurável como tempo de `attempt()` em `business_metrics`) > 100ms sustentado.
  3. Operação contrata plano em algum provedor com >100 RPM — o rate-limit deixa de proteger contra contenção de linha; avaliar Redis.
  4. Redis entra no projeto por outro motivo (roadmap pós-MVP). Nesse caso, mover cache + RateLimiter é mudança de uma variável de ambiente.
  5. Número de provedores RFB cresce (>2) e o rate-limit por provedor precisa de granularidade adicional (ex.: por endpoint, por janela diária).

## Recomendação de adiamento

Não aplicável.

---

## Aprovação humana

> Esta seção é o registro formal do aceite. Não preenchida — aguarda Alexandro.

- **Status final:** ⬜ pendente | ⬜ aceita | ⬜ rejeitada | ⬜ superseded
- **Aprovado por:** —
- **Data:** —
- **Forma do aceite:** —
- **Condicionantes do aceite:** —

### Em caso de rejeição
- **Motivo:** —
- **Próximos passos sugeridos:** —

## Referências

- IDR-004 — abstração `RfbCnpjClient` que motiva este wiring.
- IDR-005 — primário em produção (`cnpja`).
- ADR-002 §"App → RFB (externo)" — topologia da chamada e referência cruzada a este IDR.
- ADR-003 §"Tabelas técnicas" — `cache` no Postgres como driver default.
- ADR-005 §2.1 (stack Ansible), §7.7 (geração e armazenamento de credenciais) — mecanismo de secrets.
- STORY-015 — define a chave de cache `rfb:cnpj:{sha256(cnpj)}` e o TTL parametrizado.
- STORY-018 — implementa o uso de cache e RateLimiter sobre este driver.

## Histórico

- 2026-05-23 — criado pelo PO em `accepted` como ato administrativo (`decided_by: Arquiteto` provisório, sem método aplicado).
- 2026-05-23 — **reaberto e reescrito pelo Arquiteto** aplicando `decision-method.md`. Concretização do mecanismo de secrets (era "mesmo mecanismo" — agora aponta Ansible Vault §7.7 explicitamente). Adicionada análise de armadilha de contenção. Status `accepted` → `proposed`. Aguarda aprovação humana de Alexandro.
