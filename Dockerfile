# Build v3 - cache cleared 2026-06-08
FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    jpeg-dev \
    freetype-dev \
    icu-dev \
    icu-libs \
    oniguruma-dev \
    git \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    mbstring \
    zip \
    opcache \
    gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN rm -rf vendor

ENV APP_ENV=prod
ENV APP_DEBUG=0

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN composer dump-autoload --optimize --no-dev

RUN ls -la vendor/autoload.php

EXPOSE 8080

# ← Cache créé au démarrage + serveur lancé
CMD ["sh", "-c", "php bin/console cache:clear --env=prod --no-debug; php bin/console cache:warmup --env=prod --no-debug; php -S 0.0.0.0:$PORT -t public/ public/index.php"]