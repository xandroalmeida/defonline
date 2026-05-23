#!/usr/bin/env bash
# DEFOnline — bootstrap "1 comando" para o ambiente local (STORY-007 CA-4).
# Idempotente: primeira execução baixa imagens + roda composer install + migrations;
# execuções seguintes só sobem os containers.

set -euo pipefail
cd "$(dirname "$0")"

echo "==> [0/5] Instalando git hooks (idempotente)..."
./scripts/install-hooks.sh || true

echo "==> [1/5] Subindo db, mailpit e construindo a imagem do app..."
docker compose up -d --build db mailpit

echo "==> [2/5] Aguardando PostgreSQL ficar saudável..."
until docker compose exec -T db pg_isready -U postgres -d defonline >/dev/null 2>&1; do sleep 1; done

if [ ! -d app/vendor ]; then
    echo "==> [3/5] Instalando dependências do Composer (primeira execução)..."
    docker compose run --rm --no-deps -w /var/www/html web \
        composer install --no-interaction --prefer-dist --no-ansi
fi

echo "==> [4/5] Aplicando migrations no banco principal (defonline)..."
docker compose run --rm --no-deps web php artisan migrate --force

# Banco de testes — usado por Pest+Dusk, isolado do `defonline` da UI manual.
# `CREATE DATABASE` é idempotente via DO/EXCEPTION; extensões idempotentes.
# Migration roda contra ele com APP_ENV=testing (carrega .env.testing).
echo "==> [4b/5] Garantindo banco defonline_test + migrations..."
docker compose exec -T db psql -U postgres -v ON_ERROR_STOP=1 <<'SQL' >/dev/null
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = 'defonline_test') THEN
        CREATE DATABASE defonline_test OWNER defonline_app;
        GRANT CONNECT ON DATABASE defonline_test TO defonline_backup;
    END IF;
END $$;
SQL
docker compose exec -T db psql -U postgres -d defonline_test -v ON_ERROR_STOP=1 -c "
    CREATE EXTENSION IF NOT EXISTS pgcrypto;
    CREATE EXTENSION IF NOT EXISTS pg_trgm;
    CREATE EXTENSION IF NOT EXISTS citext;
    CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
" >/dev/null
docker compose run --rm --no-deps -e APP_ENV=testing web php artisan migrate --force >/dev/null

echo "==> [5/5] Subindo web, web-test, worker, scheduler e pgadmin..."
docker compose up -d web web-test worker scheduler pgadmin

echo ""
echo "✅ Ambiente DEFOnline local pronto."
echo "   - Página viva:    http://localhost:8090"
echo "   - Healthcheck:    http://localhost:8090/health"
echo "   - Readiness:      http://localhost:8090/ready"
echo "   - Mailpit UI:     http://localhost:8025"
echo "   - Postgres:       localhost:5436 (db=defonline, user=defonline_app)"
echo "   - PhpPgAdmin:     http://localhost:8091 (admin@defonline.local / dev) — DEV LOCAL APENAS"
echo ""
echo "Para derrubar tudo:               docker compose down"
echo "Para rodar testes Pest:           docker compose exec web php artisan test"
echo "Para rodar testes E2E (Dusk):     docker compose exec web php artisan dusk"
