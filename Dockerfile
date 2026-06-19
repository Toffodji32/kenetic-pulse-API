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
    openssl \
    bash \
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
ENV APP_SECRET=ChangeMeInProduction

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

RUN mkdir -p public/qrcodes public/uploads/clients public/uploads/products config/jwt

# .env a été supprimé du repo, Symfony en a besoin pour booter
RUN touch .env

# Génère les clés JWT pendant le build (plus fiable qu'au runtime)
ARG JWT_PASSPHRASE=Sauvage19
ENV JWT_PASSPHRASE=${JWT_PASSPHRASE}
RUN openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes-256-cbc \
    -pass pass:${JWT_PASSPHRASE} -pkeyopt rsa_keygen_bits:4096
RUN openssl pkey -in config/jwt/private.pem -passin pass:${JWT_PASSPHRASE} \
    -out config/jwt/public.pem -pubout

RUN chmod +x docker-entrypoint.sh

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public/ public/index.php"]
