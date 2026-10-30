---
name: analisar-banco
description: >-
  Analisar/inspecionar o banco PostgreSQL do DEFOnline (schema, tabelas, colunas,
  índices, FKs, constraints, volumes) em homolog ou local, e manter a doc de schema
  atualizada. Use quando precisar entender a estrutura do banco, escrever queries,
  investigar dados, ou regenerar defonline-docs/project-state/database-schema.md —
  em vez de descobrir o schema do zero.
---

# Analisar o banco do DEFOnline

PostgreSQL 18, Postgres-first (ADR-001/002/003 — fonte única de estado). Antes de investigar
o schema por conta própria, **leia a doc**: `defonline-docs/project-state/database-schema.md`.

## Fonte da verdade (nesta ordem)
1. **Banco aplicado** (homolog/local) — o que existe de fato. Autoritativo.
2. **Migrations** (`app/database/migrations/`) — podem estar À FRENTE do que foi deployado.
3. **A doc** (`database-schema.md`) — resumo humano; pode estar defasada. Confirme no banco antes de afirmar.

Modelos Eloquent: `app/app/Models/`. Namespace `App\` em `app/app/`.

## ⚠️ Segurança — leitura por padrão
- Homolog é ambiente com dados reais em potencial: **SELECT/introspecção à vontade; NUNCA INSERT/UPDATE/DELETE/DDL sem pedido explícito do PO** (alinha com [[feedback-smoke-read-only]]).
- Banco local (`defonline`) e de teste (`defonline_test`) podem ser escritos livremente em dev.
- Nunca rode `db:seed`/`migrate:fresh` contra homolog.

## Conexões

**Homolog (host `<HOST_HOMOLOG>` — nova infra a definir):**
```bash
# prefixo p/ qualquer psql em homolog:
ssh -i secrets/deploy-homolog.key -o IdentitiesOnly=yes -o BatchMode=yes deploy@<HOST_HOMOLOG> \
  'cd /opt/defonline && docker compose exec -T db psql -U postgres -d defonline -c "SQL_AQUI"'
```

**Local (dev — stack do `./up.sh`):**
```bash
docker compose exec -T db psql -U postgres -d defonline      -c "SQL_AQUI"   # UI manual
docker compose exec -T db psql -U postgres -d defonline_test -c "SQL_AQUI"   # Pest/Dusk
# Porta publicada: localhost:5436 (user defonline_app). Superuser: postgres.
```

Para dumps e queries longas, prefira `psql -t -A -F"|"` (sem header/alinhamento, fácil de parsear).

## Queries de introspecção (copiáveis)

```sql
-- Todas as tabelas + volume aproximado
SELECT relname, n_live_tup FROM pg_stat_user_tables ORDER BY relname;

-- Estrutura de uma tabela (colunas, tipos, nullability, default)
\d+ nome_tabela

-- Colunas via catálogo (quando \d não serve, ex.: em pipe)
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_name = 'nome_tabela' ORDER BY ordinal_position;

-- Foreign keys (origem → destino + ação de delete)
SELECT tc.table_name, kcu.column_name, ccu.table_name AS ref_table, rc.delete_rule
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage ccu ON tc.constraint_name = ccu.constraint_name
JOIN information_schema.referential_constraints rc ON tc.constraint_name = rc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY' ORDER BY tc.table_name;

-- CHECK constraints (os "enums" do projeto vivem aqui).
-- Filtra por namespace public p/ não trazer domínios internos do information_schema.
SELECT conrelid::regclass AS tabela, conname, pg_get_constraintdef(oid)
FROM pg_constraint
WHERE contype = 'c' AND connamespace = 'public'::regnamespace
ORDER BY tabela;

-- Índices de uma tabela
SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'nome_tabela';

-- Procurar uma coluna em todas as tabelas (ex.: onde vive usuario_id)
SELECT table_name, column_name FROM information_schema.columns
WHERE column_name = 'usuario_id' AND table_schema = 'public' ORDER BY table_name;
```

## Regenerar a doc de schema

Após qualquer migration nova, atualize `defonline-docs/project-state/database-schema.md`:

1. Dump autoritativo do banco aplicado:
   ```bash
   ssh -i secrets/deploy-homolog.key -o IdentitiesOnly=yes -o BatchMode=yes deploy@<HOST_HOMOLOG> \
     'cd /opt/defonline && docker compose exec -T db pg_dump -U postgres -d defonline \
        --schema-only --no-owner --no-privileges'
   ```
   (ou local: `docker compose exec -T db pg_dump -U postgres -d defonline --schema-only --no-owner --no-privileges`)
2. Compare com a doc; reescreva só as seções afetadas (mantendo o formato: tabelas de domínio, observabilidade, framework).
3. Atualize o cabeçalho: *Última sincronização*, nº de migrations (`SELECT count(*) FROM migrations;`) e de tabelas.
4. Mantenha as convenções transversais no topo — se um padrão novo surgir (ex.: tipo enum nativo), documente lá.

## Notas do domínio (contexto ao ler o schema)
- PKs de domínio são `uuid` v7; métricas/framework usam `bigint` serial.
- "Enums" são `CHECK` constraints, não tipo enum nativo — validados no INSERT.
- Unique parcial `WHERE deleted_at IS NULL` convive com soft delete.
- `evento_produto` é append-only e só recebe via `EventLogger::emit()` (PII proibida).
- Métricas usam índice **BRIN** em `inserido_em` (série temporal).
- `usuarios.email` é `citext` (case-insensitive); IP de consentimento é `inet`.
