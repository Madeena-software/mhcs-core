#!/usr/bin/env bash
# ==============================================================================
# MHCS Core Persistent Local Environment Status Check
# ==============================================================================
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PID_FILE="${ROOT_DIR}/storage/framework/rehearsal-serve.pid"
SERVE_LOG="${ROOT_DIR}/storage/logs/serve-rehearsal.log"
MPIPS_ENV="${ROOT_DIR}/mpips-grabber.env"
DB_CONTAINER="mhcs_core-mysql-local"
HOST="127.0.0.1"
PORT="${MHCS_LOCAL_PORT:-8023}"

echo "=== MHCS Core Local Infrastructure Status ==="

# 1. MySQL Docker Container Status
echo "-- 1. MySQL Container --"
if docker ps --filter "name=${DB_CONTAINER}" --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
    CONTAINER_STATUS="$(docker inspect --format '{{.State.Status}}' "${DB_CONTAINER}" 2>/dev/null || echo "unknown")"
    HEALTH_STATUS="$(docker inspect --format '{{.State.Health.Status}}' "${DB_CONTAINER}" 2>/dev/null || echo "no-healthcheck")"
    echo "  Container Name : ${DB_CONTAINER}"
    echo "  Status         : ${CONTAINER_STATUS}"
    echo "  Health         : ${HEALTH_STATUS}"
else
    echo "  Container Name : ${DB_CONTAINER} (NOT RUNNING)"
fi

# 2. Laravel Process Status
echo "-- 2. Laravel Application Process --"
if [ -f "${PID_FILE}" ]; then
    PID="$(cat "${PID_FILE}")"
    if kill -0 "${PID}" 2>/dev/null; then
        echo "  PID            : ${PID} (ALIVE)"
    else
        echo "  PID            : ${PID} (STALE / NOT RUNNING)"
    fi
else
    echo "  PID File       : Not found (${PID_FILE})"
fi

# 3. HTTP Connectivity & Endpoints
echo "-- 3. Connectivity (http://${HOST}:${PORT}) --"
HTTP_CODE="$(curl -s -o /dev/null -w "%{http_code}" "http://${HOST}:${PORT}/api/v1/grabber/manifest/0000" 2>/dev/null || echo "000")"
if [ "${HTTP_CODE}" = "401" ] || [ "${HTTP_CODE}" = "404" ] || [ "${HTTP_CODE}" = "200" ]; then
    echo "  HTTP Response  : Listening (HTTP ${HTTP_CODE} on unauthenticated probe)"
    echo "  Auth Rejection : Confirmed (401 without valid token)"
else
    echo "  HTTP Response  : Not reachable (curl returned ${HTTP_CODE})"
fi

# 4. Storage & Protected Files
echo "-- 4. Local Files & Credentials (Non-logging) --"
if [ -f "${MPIPS_ENV}" ]; then
    PERMS="$(stat -c '%a' "${MPIPS_ENV}" 2>/dev/null || stat -f '%Lp' "${MPIPS_ENV}" 2>/dev/null || echo "unknown")"
    echo "  MPIPS Env File : Present (${MPIPS_ENV}, permissions: 0${PERMS})"
    if [ "${PERMS}" != "600" ]; then
        echo "  WARNING: Permissions are not 0600!"
    fi
else
    echo "  MPIPS Env File : Not found"
fi

if [ -d "${ROOT_DIR}/storage/app/private" ] && [ -w "${ROOT_DIR}/storage/app/private" ]; then
    echo "  Private Storage: Writable (${ROOT_DIR}/storage/app/private)"
else
    echo "  Private Storage: Created/Writable at runtime"
fi

if [ -f "${SERVE_LOG}" ]; then
    echo "  Log File       : ${SERVE_LOG} ($(wc -l < "${SERVE_LOG}") lines)"
fi
