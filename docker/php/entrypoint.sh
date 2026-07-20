#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then
  php artisan key:generate --force
fi

# Migrations: fail hard outside local so production never boots on a stale schema.
case "${APP_ENV:-local}" in
  local|development|testing)
    php artisan migrate --force --no-interaction || true
    ;;
  *)
    php artisan migrate --force --no-interaction
    ;;
esac

# Demo / identity seeding is LOCAL DEVELOPMENT ONLY.
# Never inject demo admins, customers, or catalog into production/staging.
# Seeders are idempotent for identities (updateOrCreate) — safe on every local restart.
# Do NOT use SEED_DATABASE_ON_BOOT to seed production; it is ignored outside local/development.
case "${APP_ENV:-local}" in
  local|development)
    echo "Ensuring development database is seeded..."
    php artisan db:seed --force --no-interaction || true
    ;;
  *)
    if [ "${SEED_DATABASE_ON_BOOT:-false}" = "true" ]; then
      echo "REFUSING auto-seed: APP_ENV=${APP_ENV} (demo seed blocked outside local/development)"
    fi
    ;;
esac

exec "$@"
