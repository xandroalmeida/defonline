FROM php:8.5-cli-alpine

RUN apk add --no-cache \
    bash git curl unzip \
    libzip-dev oniguruma-dev libpng-dev libxml2-dev icu-dev \
    postgresql-dev postgresql-client \
    chromium chromium-chromedriver \
    nodejs npm

RUN docker-php-ext-install pdo_pgsql zip bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PATH="/var/www/html/vendor/bin:${PATH}"
WORKDIR /var/www/html

EXPOSE 8000
