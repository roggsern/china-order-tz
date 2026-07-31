#!/bin/sh
set -e

cd /var/www/html

# Ensure writable paths on the shared storage volume.
mkdir -p storage/app/public storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Public uploads (store logos, product media) — idempotent.
php artisan storage:link --force --no-interaction 2>/dev/null || true

# RC1-G4C.5 — migrations run on the API container only (queue/scheduler set SKIP_MIGRATIONS=true).
if [ "${SKIP_MIGRATIONS:-false}" != "true" ]; then
  php artisan migrate --force --no-interaction
fi

# Warm caches after runtime env is available (Compose env_file / secrets).
if [ "${SKIP_OPTIMIZE:-false}" != "true" ]; then
  php artisan config:cache --no-interaction
  php artisan route:cache --no-interaction
  php artisan view:cache --no-interaction
fi

php artisan ops:runtime-heartbeat --no-interaction 2>/dev/null || true

if [ "${QUEUE_RESTART_ON_BOOT:-false}" = "true" ]; then
  php artisan queue:restart --no-interaction 2>/dev/null || true
fi

exec "$@"
