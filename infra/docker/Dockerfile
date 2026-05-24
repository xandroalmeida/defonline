# DEFOnline — imagem única para web/worker/scheduler (ADR-002).
# Multistage: composer separa cache de deps; runtime final tem PHP + código + vendor.

# ─── Stage 1: composer + vendor ─────────────────────────────────────────────
FROM composer:2 AS composer-deps
WORKDIR /build
COPY app/composer.json app/composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --no-progress \
        --no-autoloader \
        --prefer-dist

# ─── Stage 2: Vite assets (STORY-019/024) ───────────────────────────────────
# Tailwind v4 + Inter (Bunny) compilam para public/build/{manifest.json,assets/*}.
# Views autenticadas e a landing usam @vite(['resources/css/app.css','resources/js/app.js']);
# sem manifest, qualquer rota explode com ViteManifestNotFoundException.
# public/build é gitignored → tem que ser regerado no build da imagem.
FROM node:20-alpine AS vite-build
WORKDIR /build
COPY app/package.json app/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY app/resources ./resources
COPY app/vite.config.js ./vite.config.js
RUN npm run build

# ─── Stage 3: runtime ───────────────────────────────────────────────────────
FROM php:8.5-cli-alpine

ARG APP_VERSION=dev
ENV APP_VERSION=${APP_VERSION}

RUN apk add --no-cache \
        bash git curl unzip \
        libzip-dev oniguruma-dev libpng-dev libxml2-dev icu-dev \
        postgresql-dev postgresql-client \
        chromium chromium-chromedriver \
        nodejs npm \
        && docker-php-ext-install pdo_pgsql zip bcmath \
        # PCOV — driver de cobertura leve (ADR-006 §3.1 + quality-standards §1.1/§2.2 +
        # STORY-010 CA-1 / IDR-003). Preferido a Xdebug por overhead (~10-20% vs 4-5×).
        # Habilitado por padrão na imagem, mas só ATIVA quando Pest é chamado com
        # `--coverage` — sem custo em `php artisan test` normal (pcov.enabled=0 OFF).
        && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
        && pecl install pcov \
        && docker-php-ext-enable pcov \
        && apk del .build-deps \
        && rm -rf /var/cache/apk/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PATH="/var/www/html/vendor/bin:${PATH}"
WORKDIR /var/www/html

# Copia vendor do stage 1
COPY --from=composer-deps /build/vendor /var/www/html/vendor

# Copia código da app
COPY app/ /var/www/html/

# Sobrescreve public/build com o output do stage vite-build (compila assets do design system).
COPY --from=vite-build /build/public/build /var/www/html/public/build

# bootstrap/cache e storage subdirs estão no .dockerignore — recria do zero
RUN mkdir -p bootstrap/cache \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    && chmod -R 0775 storage bootstrap/cache

# Gera autoload otimizado.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# Regera packages.php SEM as dev deps (sem Dusk/Pail/Pao/Pint/Pest).
# Sem isso, o container crash-loops com "Class Laravel\Dusk\DuskServiceProvider not found".
RUN php artisan package:discover --ansi

EXPOSE 8000
