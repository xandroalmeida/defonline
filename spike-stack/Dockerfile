FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    bash git curl unzip \
    libzip-dev oniguruma-dev libpng-dev libxml2-dev icu-dev \
    postgresql-dev postgresql-client \
    chromium chromium-chromedriver \
    nodejs npm

RUN docker-php-ext-install \
    pdo pdo_pgsql mbstring zip exif pcntl bcmath intl opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PATH="/var/www/html/vendor/bin:${PATH}"
WORKDIR /var/www/html

EXPOSE 8000
