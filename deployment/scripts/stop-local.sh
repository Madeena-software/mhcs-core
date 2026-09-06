#!/usr/bin/env bash
# ==============================================================================
# MHCS Core Persistent Local Environment Teardown / Stop
# ==============================================================================
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PID_FILE="${ROOT_DIR}/storage/framework/rehearsal-serve.pid"
COMPOSE_FILE="${ROOT_DIR}/docker-compose.local.yml"
ENV_FILE="${ROOT_DIR}/.env"

STOP_DB=false
for arg in "$@"; do
    case "${arg}" in
        --with-db|--all) STOP_DB=true ;;
    esac
done

echo "=== Stopping MHCS Core Local Server ==="
if [ -f "${PID_FILE}" ]; then
    PID="$(cat "${PID_FILE}")"
    if kill -0 "${PID}" 2>/dev/null; then
        echo "Stopping Laravel process (PID: ${PID})..."
        kill "${PID}" 2>/dev/null || true
        for i in $(seq 1 10); do
            if ! kill -0 "${PID}" 2>/dev/null; then
                break
            fi
            sleep 0.5
        done
        if kill -0 "${PID}" 2>/dev/null; then
            echo "Force-killing PID ${PID}..."
            kill -9 "${PID}" 2>/dev/null || true
        fi
        echo "Laravel process stopped."
    else
        echo "Laravel process (PID: ${PID}) was not running."
    fi
    rm -f "${PID_FILE}"
else
    echo "No PID file found. Server may not be running."
fi

if [ "${STOP_DB}" = "true" ]; then
    echo "Stopping MySQL Docker container..."
    if [ -f "${ENV_FILE}" ]; then
        docker compose --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" stop db
    else
        docker compose -f "${COMPOSE_FILE}" stop db
    fi
    echo "MySQL container stopped."
else
    echo "Note: MySQL container is left running. To also stop MySQL, run with: --with-db"
fi
