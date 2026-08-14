#!/bin/sh
# Source: Madeena-software/deploy-templates/templates/prod/standard-entrypoint.sh
# @ 569a30d4a089b0ee404ed6e963fdd2dfd96d3787
# MHCS specialization: PHP-FPM app bootstrap and shared public-asset volume.
set -eu

cd /var/www/html

mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions \
  storage/framework/views storage/logs bootstrap/cache /var/www/public-files

cp -rT /var/www/html/public/. /var/www/public-files/ 2>/dev/null || true

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

exec php-fpm --nodaemonize
