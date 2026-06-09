# Build v4
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
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN composer dump-autoload --no-dev --optimize

RUN cp vendor/symfony/runtime/autoload_runtime.template.php vendor/autoload_runtime.php 2>/dev/null || \
    echo "<?php require __DIR__.'/symfony/runtime/autoload_runtime.php';" > vendor/autoload_runtime.php

RUN ls -la vendor/autoload_runtime.php

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t public/ public/index.php"]