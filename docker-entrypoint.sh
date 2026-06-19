#!/bin/sh
set -e

# Generate JWT keys if they don't exist (first run or new deploy)
if [ ! -f config/jwt/private.pem ] && [ -n "$JWT_PASSPHRASE" ]; then
    echo "Generating JWT keys..."
    mkdir -p config/jwt
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes-256-cbc \
        -pass pass:"$JWT_PASSPHRASE" -pkeyopt rsa_keygen_bits:4096
    openssl pkey -in config/jwt/private.pem -passin pass:"$JWT_PASSPHRASE" \
        -out config/jwt/public.pem -pubout
    echo "JWT keys generated."
fi

# Warmup cache
php bin/console cache:clear --env=prod --no-debug --no-interaction 2>/dev/null || true
php bin/console cache:warmup --env=prod --no-debug --no-interaction 2>/dev/null || true

# Run migrations (retry loop in case DB is not ready yet)
echo "Running database migrations..."
for i in $(seq 1 10); do
    php bin/console doctrine:migrations:migrate --env=prod --no-interaction 2>&1 && break
    echo "Migration attempt $i failed, retrying in 3s..."
    sleep 3
done

echo "Starting PHP server..."
exec php -S 0.0.0.0:${PORT:-8080} -t public/ public/index.php
