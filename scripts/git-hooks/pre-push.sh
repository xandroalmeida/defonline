#!/usr/bin/env bash
# DEFOnline — pre-push hook obrigatório (ADR-006 §Decisão 4).
#
# Roda LOCAL antes de cada `git push`:
#  - Pint (lint)
#  - Larastan nível 6
#  - Pest suíte completa (UnitPure + Feature contra Postgres real do compose)
#  - Dusk E2E (browser Chromium dentro do container web)
#
# Falha em qualquer passo → push abortado. Bypass com `git push --no-verify` é
# **proibido por política** (ADR-006 §1.2 + culture). Use SOMENTE em emergência
# e justifique no PR.
#
# Pré-requisitos: `docker compose up -d` rodando (db + web + worker + scheduler).

set -euo pipefail

cd "$(dirname "$0")/../.."

bold() { printf '\033[1m%s\033[0m\n' "$1"; }
red()  { printf '\033[31m%s\033[0m\n' "$1"; }
grn()  { printf '\033[32m%s\033[0m\n' "$1"; }
ylw()  { printf '\033[33m%s\033[0m\n' "$1"; }

bold "▶ pre-push hook do DEFOnline"

# Aborta cedo se os containers não estão rodando.
if ! docker compose ps --status running --services 2>/dev/null | grep -q '^web$'; then
    red "✖ Container 'web' não está rodando. Execute ./up.sh antes do push."
    exit 1
fi

step() {
    local name="$1"; shift
    bold "→ $name"
    if "$@"; then
        grn "  ✓ $name ok"
    else
        red "  ✖ $name FALHOU — push abortado"
        exit 1
    fi
}

step "Pint (lint)"     docker compose exec -T web ./vendor/bin/pint --test
step "Larastan"        docker compose exec -T web ./vendor/bin/phpstan analyse --no-progress --memory-limit=512M
step "Pest (All)"      docker compose exec -T web php artisan test --testsuite=All
step "Pennant overdue" docker compose exec -T web php artisan pennant:list-overdue --fail-on-overdue

# Dusk: chromedriver precisa estar de pé dentro do container web. Inicia se necessário.
if ! docker compose exec -T web pgrep chromedriver >/dev/null 2>&1; then
    ylw "  iniciando chromedriver no container web…"
    docker compose exec -T web sh -c "chromedriver --port=9515 --whitelisted-ips='' >/tmp/chromedriver.log 2>&1 &"
    sleep 2
fi
step "Dusk E2E"        docker compose exec -T web php artisan dusk

bold "✔ pre-push verde — pode dar push."
