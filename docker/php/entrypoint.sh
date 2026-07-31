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

# Seeding policy:
# - Core seeds (roles, taxonomy, catalog scaffold, admin login) run on local boot.
# - Demo transactional data (carts, COT-FUL-* orders, fulfillments, etc.) is opt-in
#   via RUN_DEMO_SEEDS=true only. Production never auto-seeds.
case "${APP_ENV:-local}" in
  local|development)
    echo "Ensuring core development database seeds..."
    php artisan db:seed --class=CoreDatabaseSeeder --force --no-interaction || true

    if [ "${RUN_DEMO_SEEDS:-false}" = "true" ]; then
      echo "RUN_DEMO_SEEDS=true — applying demo transactional seeds..."
      php artisan db:seed --class=DemoDatabaseSeeder --force --no-interaction || true
    else
      echo "Demo seeds skipped (RUN_DEMO_SEEDS is not true). Set RUN_DEMO_SEEDS=true for demo carts/orders/fulfillment data."
    fi
    ;;
  *)
    if [ "${SEED_DATABASE_ON_BOOT:-false}" = "true" ] || [ "${RUN_DEMO_SEEDS:-false}" = "true" ]; then
      echo "REFUSING auto-seed: APP_ENV=${APP_ENV} (seeding blocked outside local/development)"
    fi
    ;;
esac

exec "$@"
