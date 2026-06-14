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
ENV JWT_PASSPHRASE=Sauvage19

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN mkdir -p public/qrcodes public/uploads/clients public/uploads/products config/jwt

# Régénère les clés JWT avec l'OpenSSL d'Alpine (compatible OpenSSL 3.x)
RUN openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes-256-cbc -pass pass:Sauvage19 -pkeyopt rsa_keygen_bits:4096
RUN openssl pkey -in config/jwt/private.pem -passin pass:Sauvage19 -out config/jwt/public.pem -pubout

EXPOSE 8080

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public/ public/index.php"]
