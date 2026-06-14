# Build v5
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

# ← Installer AVEC les scripts pour que symfony/runtime génère autoload_runtime.php
RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN ls -la vendor/autoload_runtime.php

EXPOSE 8080

CMD ["sh", "-c", "mkdir -p public/qrcodes public/uploads/clients public/uploads/products config/jwt && printf '%s' \"$JWT_SECRET_KEY\" > config/jwt/private.pem && printf '%s' \"$JWT_PUBLIC_KEY\" > config/jwt/public.pem && php -S 0.0.0.0:$PORT -t public/ public/index.php"]