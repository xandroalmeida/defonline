# DEFOnline — Schema do Banco de Dados

> **Documento vivo.** Reflete o schema **real** aplicado (fonte da verdade: banco + migrations),
> não a intenção. Regenerar após cada migration com a skill `analisar-banco` (`/analisar-banco`)
> ou manualmente (ver *Como atualizar* no fim).
>
> - **SGBD:** PostgreSQL 18 (ADR-001/002/003 — Postgres-first, fonte única de estado)
> - **Timezone do servidor:** `America/Sao_Paulo`
> - **Bancos:** `defonline` (app) · `defonline_test` (Pest/Dusk, isolado — [[project-banco-test-isolado]])
> - **Última sincronização:** 2026-08-04 · **migrations aplicadas:** 14 · **tabelas:** 21
> - **Extensões:** `citext` (email case-insensitive), `pgcrypto`, `pg_trgm`, `pg_stat_statements` — instaladas no Postgres **local** pelo initdb; no ZalkVPS (ADR-009) não há `pg_stat_statements`

---

## Convenções (padrões transversais)

- **PK das tabelas de domínio:** `uuid` (UUID v7 — ordenável no tempo). Tabelas de métrica/framework usam `bigint` serial.
- **Soft delete:** `deleted_at` nas tabelas de domínio (`usuarios`, `empresas_analisadas`, `quiz_rascunhos`, `diagnosticos`).
- **Enums no banco:** validados por `CHECK` (não por tipo enum nativo) — ver cada tabela. Typo em app falha no INSERT.
- **Unique parcial** (`WHERE deleted_at IS NULL`): permite recriar registro após soft delete sem violar unicidade.
- **Índices por tipo de carga:** `btree` (lookup), `gin jsonb_path_ops` (busca em jsonb), `brin` (séries temporais append-only, ordenadas por tempo).
- **FKs:** `ON DELETE RESTRICT` por padrão; **exceção** `term_acceptances → usuarios` é `CASCADE`.
- **Timestamps:** `timestamp(0)`. Domínio/métrica em geral `with time zone`; framework e `usuarios`/`empresas_analisadas.created_at/updated_at` em `without time zone` (inconsistência histórica — atenção ao comparar).
- **Correlação cross-process:** `request_id` (UUID v7 do middleware) presente em métricas, eventos e audit.

---

## Mapa de relacionamentos (domínio)

```
usuarios (1) ──< empresas_analisadas (1) ──< diagnosticos
   │                     │
   │                     └──< quiz_rascunhos
   ├──< diagnosticos                 (FKs RESTRICT — não apaga usuário/empresa com filhos)
   ├──< quiz_rascunhos
   └──< term_acceptances  (CASCADE — apaga aceites junto com o usuário)
```

---

## Tabelas de domínio (regra de negócio)

### `usuarios` — contas (auth)
PK `id uuid`. Persona "Roberto". Soft delete + anonimização LGPD.

| coluna | tipo | notas |
|---|---|---|
| `id` | uuid | PK |
| `email` | **citext** | **UNIQUE** — case-insensitive |
| `cpf` | varchar(11) | **UNIQUE**, nullable |
| `nome` | varchar(255) | NOT NULL |
| `telefone` | varchar(255) | nullable |
| `senha_hash` | varchar(255) | NOT NULL (bcrypt) |
| `email_confirmed_at` | timestamptz | null = email não confirmado (bloqueia login) |
| `remember_token` | varchar(100) | |
| `anonimizado_at` | timestamp | LGPD — marca conta anonimizada |
| `permissoes_admin` | jsonb | nullable — permissões de admin do backoffice (STORY-067); null = não é admin, salvo `BACKOFFICE_EMAILS` |
| `created_at`/`updated_at`/`deleted_at` | timestamp | soft delete |

### `glossario_termos` — glossário do relatório (STORY-067)
PK `id uuid`. `termo` varchar(120) **UNIQUE**, `definicao` text, `ordem` integer (índice), timestamps. Mantido na tela de Glossário do backoffice.

### `recomendacoes` — matriz de recomendações (STORY-068, PDR-016)
PK `id uuid`. **UNIQUE** `(setor, indicador, farol)`. `setor` varchar(16), `indicador` varchar(40), `farol` varchar(10), `faixa` varchar(40) (só rótulo da tela), `texto` varchar(300), `editado_em` timestamptz (null = texto de origem; o maior valor do segmento define a versão da matriz), timestamps. 117 linhas, mantidas na tela de Recomendações do backoffice.

### `empresas_analisadas` — empresas do usuário
PK `id uuid`. FK `usuario_id → usuarios` (RESTRICT). **UNIQUE parcial** `(usuario_id, documento) WHERE deleted_at IS NULL`.

| coluna | tipo | notas |
|---|---|---|
| `tipo_documento` | varchar(8) | CHECK `cnpj\|cpf` |
| `documento` | varchar(14) | CNPJ/CPF sem máscara |
| `razao_social` | text | NOT NULL |
| `nome_fantasia` | text | nullable |
| `cnae` | varchar(7) | nullable |
| `municipio` | varchar(255) / `uf` char(2) | NOT NULL |
| `situacao_cadastral` | varchar(16) | CHECK `ativa\|inapta\|baixada\|suspensa\|nao_informada` (default `nao_informada`) |
| `fonte_enriquecimento` | varchar(8) | CHECK `manual\|rfb` (default `manual`) — origem RFB via cnpja (IDR-004/005) |
| `data_fundacao` | date / `enriquecido_at` timestamptz | |
| `ficha_rfb` | jsonb | nullable — ficha RFB completa (natureza, porte, capital, CNAEs secundários, endereço, sócios, `simples`/`simei`). Só escrita por consulta à Receita (PDR-010, STORY-063); null em CPF e em empresas anteriores à STORY-063 |
| `observacoes` | text | nullable — única informação livre do usuário (até 2.000 caracteres, validado na app) |
| `personificado_por_id` | uuid | nullable, FK `usuarios` (RESTRICT) — admin da última gravação pelo "Logar como" (STORY-066); null se o próprio usuário gravou por último |

### `quiz_rascunhos` — rascunhos do quiz (23 campos, Anexo A)
PK `id uuid`. FKs `usuario_id`, `empresa_analisada_id` (RESTRICT). **UNIQUE parcial** `(usuario_id, empresa_analisada_id) WHERE deleted_at IS NULL`.

| coluna | tipo | notas |
|---|---|---|
| `quiz_payload` | jsonb | NOT NULL — respostas parciais |
| `ultimo_bloco_preenchido` | smallint | CHECK `1..4` |
| `expires_at` | timestamptz | NOT NULL — TTL do rascunho (índice `(usuario_id, expires_at)`) |
| `personificado_por_id` | uuid | nullable, FK `usuarios` — como em `empresas_analisadas` |

### `diagnosticos` — diagnóstico gerado (imutável)
PK `id uuid`. FKs `usuario_id`, `empresa_analisada_id` (RESTRICT). Índice `(empresa_analisada_id, gerado_em)`.

| coluna | tipo | notas |
|---|---|---|
| `motor_version` | varchar(16) | CHECK regex `^\d+.\d+.\d+$` (versão do motor de cálculo) |
| `matrix_version` | varchar(16) | CHECK regex `^(jan..dez)-\d{4}$` (matriz de recomendações mensal) |
| `setor` | varchar(16) | CHECK `industria\|comercio\|servicos` (MVP: só `industria`) |
| `quiz_payload` | jsonb | snapshot das respostas |
| `payload_hash` | varchar(64) | CHECK sha-256 hex — dedupe/idempotência |
| `indicadores_calculados` | jsonb | saída do motor |
| `resumo_executivo` | jsonb | destaques (STORY-040) |
| `gerado_em` | timestamptz | NOT NULL |
| `personificado_por_id` | uuid | nullable, FK `usuarios` — admin que gerou pelo "Logar como" |

### `term_acceptances` — aceites LGPD (append-only)
PK `id uuid`. FK `usuario_id → usuarios` **CASCADE**. Índice `(usuario_id, termo_tipo, aceito_at)`.

| coluna | tipo | notas |
|---|---|---|
| `termo_tipo` | varchar(32) | CHECK `termo_adesao\|lgpd\|marketing` |
| `aceito` | boolean | |
| `versao` / `conteudo_hash` | varchar | versão do termo + hash do conteúdo aceito |
| `ip` | **inet** / `user_agent` text | evidência de consentimento |
| `aceito_at` | timestamptz | default now |

---

## Observabilidade / métricas (ADR-004 — Postgres-first)

### `evento_produto` — eventos de produto (append-only, north star)
PK `id bigint`. `evento_id uuid` **UNIQUE** (idempotência). Emitido **só** via `EventLogger::emit()` (PII proibida → `PiiEmEventoException`).
- `nome_evento`, `ocorrido_em` (default now), `usuario_id`, `empresa_id`, `propriedades jsonb` (default `{}`), `request_id`.
- Índices: `(nome_evento, ocorrido_em)`, `nome_evento`, `usuario_id`, `empresa_id`, `request_id`, **GIN** `propriedades jsonb_path_ops`.
- Canônicos entregues: `quiz_iniciado`, `diagnostico_concluido` (STORY-035).
- Evento gerado no "Logar como" traz `propriedades.personificacao` com `admin_id` e `sessao_id` (STORY-066). Para excluir da métrica de produto: `NOT propriedades ? 'personificacao'`.

### `business_metrics` / `request_metrics` / `job_metrics` — séries temporais
Append-only, PK `bigint`, `request_id` de correlação, **índice BRIN** em `inserido_em` (barato p/ dados ordenados no tempo).
- `business_metrics`: `tipo`, `sucesso bool`, `duracao_ms`, `meta jsonb` (`meta.personificacao` na consulta à Receita feita pelo "Logar como").
- `request_metrics`: `path`, `method`, `status`, `duration_ms`, `usuario_id`, `empresa_id`, `personificado_por_id` (admin no "Logar como").
- `job_metrics`: `job_class`, `queue`, `status`, `duration_ms`, `tentativas`.

### `audit_logs` — trilha de auditoria
PK `uuid`. `actor_type/actor_id`, `usuario_id`, `action`, `subject_type/subject_id`, `before/after/context jsonb`. Índices em `action`, `request_id`, `usuario_id`.

### `scheduler_heartbeats` / `worker_heartbeats` — liveness (ADR-002)
- `scheduler_heartbeats`: `task` UNIQUE, `ultimo_em`.
- `worker_heartbeats`: `(hostname, queue)` UNIQUE, `ultimo_em`.

---

## Tabelas de framework (Laravel — infra, não editar à mão)

| tabela | função |
|---|---|
| `sessions` | sessão (driver `database`) — `user_id`, `last_activity` |
| `cache` / `cache_locks` | cache (driver `database`) |
| `jobs` / `job_batches` / `failed_jobs` | filas (`pdf,emails,lgpd,default`) |
| `features` | feature flags (Laravel Pennant) — UNIQUE `(name, scope)` |
| `password_reset_tokens` | reset de senha (PK `email`) |
| `migrations` | controle de migrations |

---

## Como atualizar este documento

Fonte da verdade = **banco aplicado** (não os arquivos de migration, que podem estar à frente do deploy).

**Via skill (recomendado):** `/analisar-banco` → peça "regenerar a doc do schema".

**Manual — dump do schema do servidor (ZalkVPS, ADR-009):**
```bash
ssh defonline-prod@<ZALK_HOST> \
  'cd /srv/zalk/projects/defonline-prod && . ./.env.zalk && pg_dump "$DATABASE_URL" --schema-only --no-owner --no-privileges'
```
**Manual — local (dev):**
```bash
docker compose exec -T db pg_dump -U postgres -d defonline --schema-only --no-owner --no-privileges
```
Depois reescreva as seções afetadas e atualize o cabeçalho (*Última sincronização*, contagem de migrations/tabelas).
