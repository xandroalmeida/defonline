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

# ─── Stage 2: runtime ───────────────────────────────────────────────────────
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
        && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PATH="/var/www/html/vendor/bin:${PATH}"
WORKDIR /var/www/html

# Copia vendor do stage 1
COPY --from=composer-deps /build/vendor /var/www/html/vendor

# Copia código da app
COPY app/ /var/www/html/

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
