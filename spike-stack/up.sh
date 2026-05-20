#!/usr/bin/env bash
# Spike POC bootstrapper — "1 comando" para subir Laravel 11 + Livewire 3 + Postgres 16 + Pest + Dusk.
# Idempotente: na primeira execução scaffolda Laravel; nas seguintes só sobe os serviços.

set -euo pipefail
cd "$(dirname "$0")"

if [ ! -f app/composer.json ]; then
  echo "==> [1/6] Scaffolding Laravel 11 (primeira execução)..."
  mkdir -p app
  rm -f app/.gitkeep
  docker compose run --rm --no-deps app \
    composer create-project laravel/laravel:^11.0 . --no-interaction --prefer-dist

  echo "==> [2/6] Instalando Livewire 3, Pest 3 e Laravel Dusk 8..."
  docker compose run --rm --no-deps app sh -c "\
    composer require livewire/livewire:^3.0 --no-interaction && \
    composer require --dev pestphp/pest:^3.0 pestphp/pest-plugin-laravel:^3.0 laravel/dusk:^8.0 --no-interaction -W && \
    php artisan dusk:install --no-interaction"

  echo "==> [3/6] Configurando .env (Postgres como conexão padrão)..."
  sed -i.bak \
    -e 's|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|' \
    -e 's|^# DB_HOST=.*|DB_HOST=db|;s|^DB_HOST=.*|DB_HOST=db|' \
    -e 's|^# DB_PORT=.*|DB_PORT=5432|;s|^DB_PORT=.*|DB_PORT=5432|' \
    -e 's|^# DB_DATABASE=.*|DB_DATABASE=defonline_spike|;s|^DB_DATABASE=.*|DB_DATABASE=defonline_spike|' \
    -e 's|^# DB_USERNAME=.*|DB_USERNAME=defonline|;s|^DB_USERNAME=.*|DB_USERNAME=defonline|' \
    -e 's|^# DB_PASSWORD=.*|DB_PASSWORD=defonline|;s|^DB_PASSWORD=.*|DB_PASSWORD=defonline|' \
    -e 's|^APP_URL=.*|APP_URL=http://localhost:8090|' \
    app/.env
  rm -f app/.env.bak

  echo "==> [4/6] Copiando arquivos de exemplo da spike (componente Livewire + testes + Dusk patches)..."
  mkdir -p app/app/Livewire app/resources/views/livewire app/tests/Feature app/tests/Browser
  cp -f stubs/Counter.php             app/app/Livewire/Counter.php
  cp -f stubs/counter.blade.php       app/resources/views/livewire/counter.blade.php
  cp -f stubs/welcome.blade.php       app/resources/views/welcome.blade.php
  cp -f stubs/Pest.php                app/tests/Pest.php
  cp -f stubs/CounterTest.php         app/tests/Feature/CounterTest.php
  cp -f stubs/DuskTestCase.php        app/tests/DuskTestCase.php
  cp -f stubs/CounterBrowserTest.php  app/tests/Browser/CounterBrowserTest.php
  cp -f stubs/env.dusk.local          app/.env.dusk.local
  # Remove o ExampleTest do Dusk (espera texto "Laravel" que nossa welcome.blade.php substituiu).
  rm -f app/tests/Browser/ExampleTest.php
fi

echo "==> [5/6] Subindo serviços (Postgres + Laravel)..."
docker compose up -d --build

echo "==> Aguardando Postgres ficar saudável..."
until docker compose exec -T db pg_isready -U defonline >/dev/null 2>&1; do sleep 1; done

echo "==> [6/6] Gerando APP_KEY (se vazio) e rodando migrations..."
if ! grep -qE '^APP_KEY=base64:.+' app/.env; then
  docker compose exec -T app php artisan key:generate --force
fi
# Sincroniza APP_KEY para .env.dusk.local (Dusk usa esse env durante os testes)
APP_KEY_LINE=$(grep '^APP_KEY=' app/.env)
sed -i.bak '/^APP_KEY=/d' app/.env.dusk.local && echo "$APP_KEY_LINE" >> app/.env.dusk.local && rm -f app/.env.dusk.local.bak

docker compose exec -T app php artisan config:clear >/dev/null
docker compose exec -T app php artisan migrate --force

echo ""
echo "✅ Spike pronta."
echo "   - App:        http://localhost:8090"
echo "   - Postgres:   localhost:5436 (db: defonline_spike, user/pass: defonline/defonline)"
echo ""
echo "Para rodar a suíte de testes:    ./test.sh"
echo "Para derrubar:                   docker compose down"
