FROM php:8.3-cli-alpine

# Dépendances système + extensions PHP
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

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Dossier de travail
WORKDIR /app

# Copier composer files
COPY composer.json composer.lock ./

# Installer les dépendances
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copier tout le reste
COPY . .

# Dump autoload
RUN composer dump-autoload --optimize --no-dev

# Variables
ENV APP_ENV=prod
ENV APP_DEBUG=0

# Cache Symfony
RUN php bin/console cache:clear --env=prod --no-debug || true
RUN php bin/console cache:warmup --env=prod --no-debug || true

# Port
EXPOSE 8080

# Démarrer
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t public/ public/index.php"]