#!/bin/sh
# Source: Madeena-software/deploy-templates/templates/prod/standard-entrypoint.sh
# @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787
# MHCS specialization: PHP-FPM app bootstrap and shared public-asset volume.
set -eu

cd /var/www/html

mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
  storage/framework/views storage/logs bootstrap/cache /var/www/public-files

cp -rT /var/www/html/public/. /var/www/public-files/ 2>/dev/null || true

# If arguments are provided that are NOT starting php-fpm, exec them directly
# (e.g. queue worker, scheduler, image-worker, or custom commands).
if [ "$#" -gt 0 ] && [ "$1" != "php-fpm" ]; then
  exec "$@"
fi

# Pre-warm and finalize Laravel runtime caches before PHP-FPM begins serving.
# With opcache.validate_timestamps=0, PHP-FPM compiles these artifacts into shared
# memory on first access and will never revalidate disk timestamps. Finalizing them
# here guarantees FPM never serves with stale or missing bytecode.
# If cache finalization fails, the entrypoint fails closed and FPM never starts.
if [ "${SKIP_ENTRYPOINT_CACHE_WARM:-0}" != "1" ]; then
  echo "[entrypoint] Finalizing Laravel runtime caches before PHP-FPM startup..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  echo "[entrypoint] Runtime caches finalized successfully. Starting PHP-FPM..."
fi

exec php-fpm --nodaemonize
