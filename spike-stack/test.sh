#!/usr/bin/env bash
# Roda os testes da spike: Pest (unit/feature) + Laravel Dusk (E2E em Chromium real).

set -euo pipefail
cd "$(dirname "$0")"

echo "==> Pest (Feature — inclui Livewire + Postgres)..."
docker compose exec -T app php artisan test --testsuite=Feature

echo ""
echo "==> Iniciando chromedriver headless em background dentro do container..."
docker compose exec -T app sh -c "pgrep -x chromedriver >/dev/null 2>&1 || (chromedriver --port=9515 --whitelisted-ips='' --allowed-origins='*' >/tmp/chromedriver.log 2>&1 &)"
sleep 2

echo "==> Dusk (E2E em Chromium real)..."
docker compose exec -T app php artisan dusk
