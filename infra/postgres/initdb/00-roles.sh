#!/usr/bin/env bash
# First-boot do cluster Postgres (ADR-005 §7.3, §7.4 + IDR pós-STORY-007).
#
# - Cria roles defonline_app e defonline_backup (princípio do menor privilégio).
# - Habilita extensões adotadas no MVP: pgcrypto, pg_trgm, citext, pg_stat_statements.
#   IDR: ADR-005 §7.3 prevê extensões via migration Laravel; movido para init script
#   porque (a) pg_stat_statements exige superuser; (b) o role defonline_app não tem
#   CREATE EXTENSION mesmo para trusted extensions, e conceder esse privilégio quebra
#   o princípio do menor privilégio mais do que o ganho de "versionamento da extensão".
#   Documentado em IDR-001 (criado nesta sessão).

set -euo pipefail

APP_DB_USER="${APP_DB_USER:-defonline_app}"
APP_DB_PASSWORD="${APP_DB_PASSWORD:?missing APP_DB_PASSWORD}"
BACKUP_DB_USER="${BACKUP_DB_USER:-defonline_backup}"
BACKUP_DB_PASSWORD="${BACKUP_DB_PASSWORD:?missing BACKUP_DB_PASSWORD}"

psql --variable=ON_ERROR_STOP=1 \
     --username "$POSTGRES_USER" \
     --dbname "$POSTGRES_DB" <<-SQL
    -- Extensões adotadas no MVP (ADR-003 §Decisão 7 + ADR-005 §7.3).
    CREATE EXTENSION IF NOT EXISTS pgcrypto;
    CREATE EXTENSION IF NOT EXISTS pg_trgm;
    CREATE EXTENSION IF NOT EXISTS citext;
    CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

    -- App role: usado pela aplicação em runtime (web/worker/scheduler).
    CREATE ROLE ${APP_DB_USER} WITH LOGIN PASSWORD '${APP_DB_PASSWORD}';
    GRANT CONNECT ON DATABASE ${POSTGRES_DB} TO ${APP_DB_USER};
    GRANT USAGE ON SCHEMA public TO ${APP_DB_USER};
    GRANT CREATE ON SCHEMA public TO ${APP_DB_USER};
    ALTER DEFAULT PRIVILEGES IN SCHEMA public
        GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO ${APP_DB_USER};
    ALTER DEFAULT PRIVILEGES IN SCHEMA public
        GRANT USAGE, SELECT ON SEQUENCES TO ${APP_DB_USER};

    -- Backup role: somente leitura (pg_dump).
    CREATE ROLE ${BACKUP_DB_USER} WITH LOGIN PASSWORD '${BACKUP_DB_PASSWORD}';
    GRANT CONNECT ON DATABASE ${POSTGRES_DB} TO ${BACKUP_DB_USER};
    GRANT pg_read_all_data TO ${BACKUP_DB_USER};

    -- Banco dedicado para a suíte de testes (Pest + RefreshDatabase). Isolado
    -- do banco \`${POSTGRES_DB}\` que serve a UI em smoke manual local — antes
    -- (até 2026-05-23) os dois compartilhavam o mesmo banco e qualquer pre-push
    -- apagava o que o PO tinha cadastrado pela UI. Override correspondente em
    -- app/phpunit.xml (\`DB_DATABASE=defonline_test\` force=true).
    CREATE DATABASE defonline_test OWNER ${APP_DB_USER};
    GRANT CONNECT ON DATABASE defonline_test TO ${BACKUP_DB_USER};
SQL

# Extensões precisam ser instaladas no banco de teste também — `CREATE EXTENSION`
# é per-database, e as migrations Laravel não cobrem extensões (IDR-001).
psql --variable=ON_ERROR_STOP=1 \
     --username "$POSTGRES_USER" \
     --dbname defonline_test <<-SQL
    CREATE EXTENSION IF NOT EXISTS pgcrypto;
    CREATE EXTENSION IF NOT EXISTS pg_trgm;
    CREATE EXTENSION IF NOT EXISTS citext;
    CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
SQL
