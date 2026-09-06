#!/usr/bin/env bash
# ==============================================================================
# MHCS Core Persistent Local Environment Startup
# Starts local MySQL in Docker and persistent Laravel process on 127.0.0.1:8023
# ==============================================================================
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/docker-compose.local.yml"
ENV_FILE="${ROOT_DIR}/.env"
ENV_EXAMPLE="${ROOT_DIR}/.env.example"
LOG_DIR="${ROOT_DIR}/storage/logs"
RUN_DIR="${ROOT_DIR}/storage/framework"
SERVE_LOG="${LOG_DIR}/serve-rehearsal.log"
PID_FILE="${RUN_DIR}/rehearsal-serve.pid"
MPIPS_ENV="${ROOT_DIR}/mpips-grabber.env"
TOKEN_FILE="${RUN_DIR}/grabber.token"

HOST="127.0.0.1"
PORT="${MHCS_LOCAL_PORT:-8023}"
DB_CONTAINER="mhcs_core-mysql-local"
DB_PORT="${MHCS_LOCAL_DB_PORT:-3306}"

echo "=== 1. Inspecting Docker Availability ==="
command -v docker >/dev/null 2>&1 || { echo "ERROR: docker command not found" >&2; exit 1; }
docker info >/dev/null 2>&1 || { echo "ERROR: Docker daemon is not running" >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "ERROR: Docker Compose is not available" >&2; exit 1; }

echo "=== 2. Preparing Local Environment Configuration ==="
mkdir -p "${LOG_DIR}" "${RUN_DIR}"

set_env_key() {
    local key="$1"
    local val="$2"
    if grep -q "^${key}=" "${ENV_FILE}" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=${val}|" "${ENV_FILE}"
    else
        echo "${key}=${val}" >> "${ENV_FILE}"
    fi
}

get_env_key() {
    local key="$1"
    sed -n "s/^${key}=//p" "${ENV_FILE}" 2>/dev/null | head -n 1
}

# Ensure base .env exists
if [ ! -f "${ENV_FILE}" ]; then
    if [ -f "${ENV_EXAMPLE}" ]; then
        cp "${ENV_EXAMPLE}" "${ENV_FILE}"
    else
        touch "${ENV_FILE}"
    fi
fi

# Configure local topology and secrets
set_env_key "APP_NAME" '"MHCS Core"'
set_env_key "APP_ENV" "local"
set_env_key "APP_DEBUG" "true"
set_env_key "APP_URL" "http://${HOST}:${PORT}"
set_env_key "DB_CONNECTION" "mysql"
set_env_key "DB_HOST" "${HOST}"
set_env_key "DB_PORT" "${DB_PORT}"
set_env_key "DB_DATABASE" "mhcs_core"
set_env_key "DB_USERNAME" "mhcs_local"
set_env_key "QUEUE_CONNECTION" "database"
set_env_key "CACHE_STORE" "database"
set_env_key "SESSION_DRIVER" "database"
set_env_key "FILESYSTEM_DISK" "local"
set_env_key "MHCS_PRIVATE_OBJECT_DISK" "local"
set_env_key "MHCS_MANIFEST_KEY_ID" "local-manifest-key"

# Ensure database passwords exist
if [ -z "$(get_env_key 'DB_PASSWORD')" ]; then
    set_env_key "DB_PASSWORD" "$(php -r 'echo bin2hex(random_bytes(16));')"
fi
if [ -z "$(get_env_key 'DB_ROOT_PASSWORD')" ]; then
    set_env_key "DB_ROOT_PASSWORD" "$(php -r 'echo bin2hex(random_bytes(16));')"
fi

# Ensure cryptographic keys exist
if [ -z "$(get_env_key 'APP_KEY')" ]; then
    set_env_key "APP_KEY" "base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi
if [ -z "$(get_env_key 'MHCS_IDENTIFIER_KEY')" ]; then
    set_env_key "MHCS_IDENTIFIER_KEY" "$(php -r 'echo bin2hex(random_bytes(16));')"
fi
if [ -z "$(get_env_key 'MHCS_ACCESS_GRANT_KEY')" ]; then
    set_env_key "MHCS_ACCESS_GRANT_KEY" "$(php -r 'echo bin2hex(random_bytes(16));')"
fi
if [ -z "$(get_env_key 'MHCS_MANIFEST_KEY')" ]; then
    set_env_key "MHCS_MANIFEST_KEY" "$(php -r 'echo bin2hex(random_bytes(16));')"
fi

# Disable AI PACS (local non-production)
set_env_key "AI_PACS_URL" ""
set_env_key "AI_PACS_USERNAME" ""
set_env_key "AI_PACS_PASSWORD" ""

chmod 0600 "${ENV_FILE}"

echo "=== 3. Starting Local MySQL Container (${DB_CONTAINER}) ==="
docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" up -d db

echo "Waiting for MySQL container to report healthy..."
for attempt in $(seq 1 45); do
    health="$(docker inspect --format '{{.State.Health.Status}}' "${DB_CONTAINER}" 2>/dev/null || true)"
    if [ "${health}" = "healthy" ]; then
        echo "MySQL is healthy (attempt ${attempt})."
        break
    fi
    if [ "${attempt}" -eq 45 ]; then
        echo "ERROR: MySQL container failed to become healthy. Check: docker logs ${DB_CONTAINER}" >&2
        exit 1
    fi
    sleep 1
done

echo "=== 4. Running Migrations Against Local MySQL ==="
php "${ROOT_DIR}/artisan" migrate --force

echo "=== 5. Provisioning Synthetic Grabber Rehearsal Context ==="
# Provision context without printing credentials; writes to protected mpips-grabber.env
set +x
php "${ROOT_DIR}/artisan" mhcs:provision-grabber-rehearsal \
    --base-url="http://${HOST}:${PORT}" \
    --env-out="${MPIPS_ENV}" \
    --token-file="${TOKEN_FILE}"

if [ -f "${MPIPS_ENV}" ]; then
    chmod 0600 "${MPIPS_ENV}"
fi
if [ -f "${TOKEN_FILE}" ]; then
    chmod 0600 "${TOKEN_FILE}"
fi

# Also ensure /var/www/mpips gets the gitignored env file if available
if [ -d "/var/www/mpips" ]; then
    cp "${MPIPS_ENV}" "/var/www/mpips/.env.grabber.local"
    chmod 0600 "/var/www/mpips/.env.grabber.local"
fi

echo "=== 6. Starting Persistent MHCS Core Server on ${HOST}:${PORT} ==="
# Check if already running
if [ -f "${PID_FILE}" ]; then
    EXISTING_PID="$(cat "${PID_FILE}")"
    if kill -0 "${EXISTING_PID}" 2>/dev/null; then
        echo "Server is already running with PID ${EXISTING_PID}."
    else
        rm -f "${PID_FILE}"
    fi
fi

if [ ! -f "${PID_FILE}" ]; then
    # Start background process that survives shell exit
    nohup php "${ROOT_DIR}/artisan" serve --host="${HOST}" --port="${PORT}" >> "${SERVE_LOG}" 2>&1 &
    SERVER_PID=$!
    echo "${SERVER_PID}" > "${PID_FILE}"
    echo "Started MHCS Core server with PID: ${SERVER_PID}"
fi

# Wait for server to become responsive
echo "Waiting for MHCS Core server to respond at http://${HOST}:${PORT}..."
SERVER_UP=false
for attempt in $(seq 1 30); do
    HTTP_STATUS="$(curl -s -o /dev/null -w "%{http_code}" "http://${HOST}:${PORT}/api/v1/grabber/manifest/0000" || true)"
    if [ "${HTTP_STATUS}" = "401" ] || [ "${HTTP_STATUS}" = "404" ] || [ "${HTTP_STATUS}" = "200" ]; then
        SERVER_UP=true
        echo "MHCS Core server is responsive (HTTP status: ${HTTP_STATUS})."
        break
    fi
    sleep 0.5
done

if [ "${SERVER_UP}" != "true" ]; then
    echo "ERROR: Server did not respond within timeout. Log contents:" >&2
    tail -n 20 "${SERVE_LOG}" >&2
    exit 1
fi

echo ""
echo "=== MHCS CORE LOCAL ENVIRONMENT READY (PERSISTENT) ==="
echo "MySQL Container  : ${DB_CONTAINER} ($(docker inspect --format '{{.State.Health.Status}}' "${DB_CONTAINER}"))"
echo "Laravel PID      : $(cat "${PID_FILE}")"
echo "Bound URL        : http://${HOST}:${PORT}"
echo "MPIPS Env File   : ${MPIPS_ENV} (mode 0600)"
echo "Log File         : ${SERVE_LOG}"
echo ""
echo "To check status  : ./deployment/scripts/status-local.sh"
echo "To stop server   : ./deployment/scripts/stop-local.sh"
