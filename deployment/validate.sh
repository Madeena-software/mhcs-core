#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
required=(
  Dockerfile
  .dockerignore
  docker-compose.prod.yml
  docker/entrypoint.sh
  docker/nginx.conf
  docker/php.ini
  docker/supervisord.conf
  deployment/README.md
)

for file in "${required[@]}"; do
  test -f "$root/$file"
done

for file in \
  "$root/deployment/README.md" \
  "$root/Dockerfile" \
  "$root/docker-compose.prod.yml" \
  "$root/docker/entrypoint.sh" \
  "$root/docker/nginx.conf" \
  "$root/docker/php.ini" \
  "$root/docker/supervisord.conf" \
  "$root/.dockerignore"; do
  grep -q '569a30d4a089b0ee404ed6e963fdd2dfd96d3787' "$file"
done
grep -q 'queue:' "$root/docker-compose.prod.yml"
grep -q 'scheduler:' "$root/docker-compose.prod.yml"
grep -q 'image-worker:' "$root/docker-compose.prod.yml"
grep -q 'mpips_private' "$root/docker-compose.prod.yml"
grep -q 'external: true' "$root/docker-compose.prod.yml"
grep -q 'app_public:/var/www/public-files' "$root/docker-compose.prod.yml"
grep -q 'cp -rT /var/www/html/public/. /var/www/public-files/' "$root/docker/entrypoint.sh"
grep -q 'ports:' "$root/docker-compose.prod.yml"
grep -q 'MPIPS_NETWORK_NAME' "$root/docker-compose.prod.yml"
grep -q 'MHCS_ENV_FILE' "$root/docker-compose.prod.yml"

if grep -q '^  mpips:' "$root/docker-compose.prod.yml"; then
  echo 'MHCS deployment must not define the separately owned MPIPS service' >&2
  exit 1
fi

if rg -n -i --glob '!deployment/README.md' '(ssh|scp|rsync|production\.example|staging\.example|BEGIN (RSA|OPENSSH|PRIVATE) KEY|https?://[A-Za-z0-9.-]+/)' "$root/.github" "$root/docker-compose.prod.yml" "$root/Dockerfile" "$root/docker" >/dev/null; then
  echo 'deployment contains a forbidden live-environment or secret pattern' >&2
  exit 1
fi

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DATABASE_IMAGE=validation/database \
  CACHE_IMAGE=validation/cache \
  MHCS_IMAGE=validation/mhcs \
  DB_ROOT_PASSWORD=validation-root \
  DB_DATABASE=validation \
  DB_USERNAME=validation \
  DB_PASSWORD=validation-password \
  APP_PORT=18080 \
  MPIPS_NETWORK_NAME=validation-mpips \
  MHCS_ENV_FILE=/dev/null \
    docker compose --env-file /dev/null -f "$root/docker-compose.prod.yml" config >/dev/null
else
  echo 'docker compose validation skipped: Docker Compose is unavailable'
fi

echo 'deployment static validation passed'
