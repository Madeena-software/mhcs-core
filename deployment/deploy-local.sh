#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/docker-compose.local.yml"
ENV_TEMPLATE="${ROOT_DIR}/.env.example"
ENV_FILE="${ROOT_DIR}/.env"
APP_SLUG="mhcs_core"
DB_CONTAINER="${APP_SLUG}-mysql-local"
DB_PORT="${MHCS_LOCAL_DB_PORT:-3306}"

FRESH=false
NO_START=false

usage() {
    cat <<'EOF'
MHCS Core local development setup

Usage:
  ./deploy-local.sh              Start MySQL, prepare the app, and run composer dev
  ./deploy-local.sh --no-start   Prepare the app without starting the dev processes
  ./deploy-local.sh --fresh      Reset the local database and seed it
  ./deploy-local.sh --help       Show this help

Architecture:
  PHP, Composer, and NPM run on the host.
  MySQL 8.4 runs in Docker at 127.0.0.1:${DB_PORT}.

Environment:
  Set MHCS_LOCAL_DB_PORT to use a different host port.
  Generated local secrets are written only to the ignored .env file.
EOF
}

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || die "Required command not found: $1"
}

set_env() {
    local key="$1" value="$2"

    if grep -q "^${key}=" "$ENV_FILE" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
    fi
}

env_value() {
    local key="$1"

    sed -n "s/^${key}=//p" "$ENV_FILE" | head -n 1
}

ensure_secret() {
    local key="$1"

    if [[ -z "$(env_value "$key")" ]]; then
        set_env "$key" "$(php -r 'echo bin2hex(random_bytes(32));')"
    fi
}

for argument in "$@"; do
    case "$argument" in
        --fresh) FRESH=true ;;
        --no-start) NO_START=true ;;
        --help|-h) usage; exit 0 ;;
        *) usage; die "Unknown option: $argument" ;;
    esac
done

if [[ "$FRESH" == true ]]; then
    [[ -t 0 ]] || die '--fresh requires an interactive confirmation.'
    read -r -p 'This drops all local MySQL tables. Type RESET to continue: ' confirmation
    [[ "$confirmation" == RESET ]] || die 'Database reset cancelled.'
fi

require_command php
require_command composer
require_command node
require_command npm
require_command docker
docker compose version >/dev/null 2>&1 || die 'Docker Compose is unavailable.'
docker info >/dev/null 2>&1 || die 'Docker daemon is not running.'

php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' || die 'PHP 8.4 or newer is required.'

for extension in pdo_mysql mbstring xml intl bcmath pcntl; do
    php -m | grep -qi "^${extension}$" || die "Missing PHP extension: ${extension}"
done

[[ -f "$ENV_TEMPLATE" ]] || die "Missing environment template: $ENV_TEMPLATE"
[[ -f "$COMPOSE_FILE" ]] || die "Missing Docker Compose file: $COMPOSE_FILE"

cd "$ROOT_DIR"

if [[ ! -f "$ENV_FILE" ]]; then
    cp "$ENV_TEMPLATE" "$ENV_FILE"
fi

set_env APP_ENV local
set_env APP_DEBUG true
set_env APP_URL http://localhost:8000
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT "$DB_PORT"
set_env DB_DATABASE mhcs_core
set_env DB_USERNAME mhcs_local
set_env QUEUE_CONNECTION database
set_env CACHE_STORE database
set_env SESSION_DRIVER database
set_env FILESYSTEM_DISK local
set_env MHCS_MANIFEST_KEY_ID local

for secret in APP_KEY DB_PASSWORD DB_ROOT_PASSWORD MHCS_IDENTIFIER_KEY MHCS_OBJECT_ENCRYPTION_KEY MHCS_ACCESS_GRANT_KEY MHCS_MANIFEST_KEY; do
    ensure_secret "$secret"
done

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" up -d db

for attempt in $(seq 1 30); do
    health="$(docker inspect --format '{{.State.Health.Status}}' "$DB_CONTAINER" 2>/dev/null || true)"
    if [[ "$health" == healthy ]]; then
        break
    fi

    [[ "$attempt" -lt 30 ]] || die "MySQL did not become healthy. Check: docker logs $DB_CONTAINER"
    sleep 2
done

composer install --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan config:clear --ansi
php artisan key:generate --force --ansi

if [[ "$FRESH" == true ]]; then
    php artisan migrate:fresh --seed --force
else
    php artisan migrate --force
fi

php artisan storage:link --force 2>/dev/null || true
mkdir -p storage/enterprise_data_local
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

printf '\nMHCS Core local environment is ready.\n'
printf 'App: http://localhost:8000\n'
printf 'MySQL: 127.0.0.1:%s (%s)\n' "$DB_PORT" "$DB_CONTAINER"

if [[ "$NO_START" == true ]]; then
    printf 'Start development processes with: composer dev\n'
else
    exec composer dev
fi
