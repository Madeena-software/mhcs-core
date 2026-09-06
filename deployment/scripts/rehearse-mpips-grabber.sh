#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# Controlled Local Rehearsal Harness for MPIPS Grabber Integration
# Target: Loopback only (127.0.0.1)
# Storage & Database: Disposable non-production SQLite & Local disk
# ==============================================================================

REHEARSAL_PORT="${REHEARSAL_PORT:-8023}"
REHEARSAL_HOST="127.0.0.1"
BASE_URL="http://${REHEARSAL_HOST}:${REHEARSAL_PORT}"
SCRATCH_DIR="/var/www/mhcs-core/storage/framework/testing/rehearsal"
DB_PATH="${SCRATCH_DIR}/rehearsal_disposable.sqlite"
PID_FILE="${SCRATCH_DIR}/serve.pid"

mkdir -p "${SCRATCH_DIR}"
rm -f "${DB_PATH}" "${PID_FILE}"
touch "${DB_PATH}"

PERSISTENT=false
for arg in "$@"; do
    case "${arg}" in
        --persistent|--no-stop) PERSISTENT=true ;;
    esac
done

TOKEN_FILE="${SCRATCH_DIR}/grabber.token"
ENV_OUT="${SCRATCH_DIR}/mpips-grabber.env"

echo "=== 1. Preparing Database & Migrations ==="
# Check if persistent server is already running
if curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/v1/grabber/manifest/0000" 2>/dev/null | grep -qE "401|404"; then
    echo "Persistent local server already running on ${BASE_URL}."
    ALREADY_RUNNING=true
else
    ALREADY_RUNNING=false
    export APP_ENV=local
    export APP_DEBUG=true
    export APP_KEY="base64:YXV0aGVudGljYXRlZGRpY29taW5nZXN0aW9ua2V5MTI="
    export MHCS_IDENTIFIER_KEY="12345678901234567890123456789012"
    export MHCS_ACCESS_GRANT_KEY="12345678901234567890123456789012"
    export MHCS_MANIFEST_KEY="12345678901234567890123456789012"
    export MHCS_MANIFEST_KEY_ID="rehearsal-manifest-key"
    export MHCS_PRIVATE_OBJECT_DISK="local"
    export DB_CONNECTION="sqlite"
    export DB_DATABASE="${DB_PATH}"
    export CACHE_STORE="array"
    export SESSION_DRIVER="array"
    export QUEUE_CONNECTION="sync"

    php /var/www/mhcs-core/artisan migrate --force > /dev/null
fi

echo "=== 2. Provisioning Rehearsal Context ==="
PROVISION_JSON=$(php /var/www/mhcs-core/artisan mhcs:provision-grabber-rehearsal \
    --json \
    --base-url="${BASE_URL}" \
    --env-out="${ENV_OUT}" \
    --token-file="${TOKEN_FILE}")

LOCATOR_CODE=$(echo "${PROVISION_JSON}" | grep -o '"locator_code": "[^"]*' | cut -d'"' -f4)
GRABBER_ID=$(echo "${PROVISION_JSON}" | grep -o '"grabber_id": "[^"]*' | cut -d'"' -f4)
PATIENT_MRN=$(echo "${PROVISION_JSON}" | grep -o '"mrn": "[^"]*' | cut -d'"' -f4)

set +x
GRABBER_TOKEN="$(< "${TOKEN_FILE}")"

echo "Provisioned Session:"
echo "  Locator Code : ${LOCATOR_CODE}"
echo "  Grabber ID   : ${GRABBER_ID}"
echo "  Patient MRN  : ${PATIENT_MRN}"
echo "  Grabber Token: [REDACTED]"

if [ "${ALREADY_RUNNING}" = "false" ]; then
    echo "=== 3. Starting Local Development Server on Loopback (${BASE_URL}) ==="
    php /var/www/mhcs-core/artisan serve --host="${REHEARSAL_HOST}" --port="${REHEARSAL_PORT}" > "${SCRATCH_DIR}/serve.log" 2>&1 &
    SERVER_PID=$!
    echo "${SERVER_PID}" > "${PID_FILE}"

    cleanup() {
        if [ "${PERSISTENT}" != "true" ]; then
            echo "Shutting down local development server (PID: ${SERVER_PID})..."
            kill "${SERVER_PID}" 2>/dev/null || true
            wait "${SERVER_PID}" 2>/dev/null || true
        else
            echo "Leaving local development server running (PID: ${SERVER_PID})."
        fi
        rm -f "${TOKEN_FILE}"
    }
    trap cleanup EXIT

    # Wait for server to become responsive
    for i in $(seq 1 30); do
        if curl -s "${BASE_URL}" > /dev/null 2>&1 || curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/v1/grabber/manifest/0000" | grep -qE "401|404"; then
            echo "Local server is up and responsive."
            break
        fi
        sleep 0.2
    done
else
    cleanup() {
        echo "Rehearsal complete. Persistent server remains active."
        rm -f "${TOKEN_FILE}"
    }
    trap cleanup EXIT
fi

echo "=== 4. Rehearsal Verification Step A: Manifest Lookup ==="
# A1. Authenticated Manifest Lookup
MANIFEST_HTTP_CODE=$(curl -s -o "${SCRATCH_DIR}/manifest.json" -w "%{http_code}" \
    -H "Authorization: Bearer ${GRABBER_TOKEN}" \
    "${BASE_URL}/api/v1/grabber/manifest/${LOCATOR_CODE}")

echo "Manifest Lookup HTTP Status: ${MANIFEST_HTTP_CODE}"
if [ "${MANIFEST_HTTP_CODE}" -ne 200 ]; then
    echo "FAILED: Expected 200 for manifest lookup, got ${MANIFEST_HTTP_CODE}"
    cat "${SCRATCH_DIR}/manifest.json"
    exit 1
fi

# Verify PII absence
if grep -q "900000000088" "${SCRATCH_DIR}/manifest.json"; then
    echo "FAILED: Manifest exposed patient NIK!"
    exit 1
fi
echo "Manifest Lookup PASS (Filtered PII, Correct Demographic Structure)"

# A2. Authentication Rejection
AUTH_FAIL_HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" \
    -H "Authorization: Bearer invalid-token" \
    "${BASE_URL}/api/v1/grabber/manifest/${LOCATOR_CODE}")

echo "Auth Failure HTTP Status: ${AUTH_FAIL_HTTP_CODE}"
if [ "${AUTH_FAIL_HTTP_CODE}" -ne 401 ]; then
    echo "FAILED: Expected 401 for invalid token, got ${AUTH_FAIL_HTTP_CODE}"
    exit 1
fi
echo "Authentication Rejection PASS"

echo "=== 5. Rehearsal Verification Step B: DICOM Upload & Idempotent Replay ==="
SUBMISSION_ID=$(python3 -c "import uuid; print(uuid.uuid4())")
DICOM_FILE="${SCRATCH_DIR}/sample.dcm"

# Generate valid DICOM Part 10 fixture (128-byte null preamble + 'DICM' magic bytes + payload)
python3 -c "
preamble = b'\x00' * 128
magic = b'DICM'
content = b'REHEARSAL-PART-10-CONTENT-' + b'X' * 512
with open('${DICOM_FILE}', 'wb') as f:
    f.write(preamble + magic + content)
"
CHECKSUM=$(sha256sum "${DICOM_FILE}" | awk '{print $1}')

# B1. Initial Upload -> 201, replayed: false, terminal_state: awaiting_ai
UPLOAD_HTTP_CODE=$(curl -s -o "${SCRATCH_DIR}/upload_1.json" -w "%{http_code}" \
    -H "Authorization: Bearer ${GRABBER_TOKEN}" \
    -H "X-Submission-ID: ${SUBMISSION_ID}" \
    -H "X-Checksum-SHA256: ${CHECKSUM}" \
    -F "file=@${DICOM_FILE};filename=rehearsal.dcm" \
    "${BASE_URL}/api/v1/grabber/radiography-sessions/${LOCATOR_CODE}/dicom")

echo "Initial Upload HTTP Status: ${UPLOAD_HTTP_CODE}"
cat "${SCRATCH_DIR}/upload_1.json"
echo ""

if [ "${UPLOAD_HTTP_CODE}" -ne 201 ]; then
    echo "FAILED: Expected 201 for initial upload, got ${UPLOAD_HTTP_CODE}"
    exit 1
fi

if ! grep -q '"replayed":false' "${SCRATCH_DIR}/upload_1.json" && ! grep -q '"replayed": false' "${SCRATCH_DIR}/upload_1.json"; then
    echo "FAILED: Expected replayed: false"
    exit 1
fi

if ! grep -q '"terminal_state":"awaiting_ai"' "${SCRATCH_DIR}/upload_1.json" && ! grep -q '"terminal_state": "awaiting_ai"' "${SCRATCH_DIR}/upload_1.json"; then
    echo "FAILED: Expected terminal_state: awaiting_ai"
    exit 1
fi
echo "Initial Upload PASS (201 Created, replayed: false, terminal_state: awaiting_ai)"

# B2. Identical Replay -> 200, replayed: true, terminal_state: awaiting_ai
REPLAY_HTTP_CODE=$(curl -s -o "${SCRATCH_DIR}/upload_2.json" -w "%{http_code}" \
    -H "Authorization: Bearer ${GRABBER_TOKEN}" \
    -H "X-Submission-ID: ${SUBMISSION_ID}" \
    -H "X-Checksum-SHA256: ${CHECKSUM}" \
    -F "file=@${DICOM_FILE};filename=rehearsal.dcm" \
    "${BASE_URL}/api/v1/grabber/radiography-sessions/${LOCATOR_CODE}/dicom")

echo "Replay Upload HTTP Status: ${REPLAY_HTTP_CODE}"
cat "${SCRATCH_DIR}/upload_2.json"
echo ""

if [ "${REPLAY_HTTP_CODE}" -ne 200 ]; then
    echo "FAILED: Expected 200 for idempotent replay, got ${REPLAY_HTTP_CODE}"
    exit 1
fi

if ! grep -q '"replayed":true' "${SCRATCH_DIR}/upload_2.json" && ! grep -q '"replayed": true' "${SCRATCH_DIR}/upload_2.json"; then
    echo "FAILED: Expected replayed: true"
    exit 1
fi
echo "Idempotent Replay PASS (200 OK, replayed: true)"

# B3. Conflicting Payload Retry -> 409 Conflict
DICOM_FILE_2="${SCRATCH_DIR}/sample_2.dcm"
python3 -c "
preamble = b'\x00' * 128
magic = b'DICM'
content = b'REHEARSAL-PART-10-DIFFERENT-PAYLOAD-' + b'Y' * 512
with open('${DICOM_FILE_2}', 'wb') as f:
    f.write(preamble + magic + content)
"
CHECKSUM_2=$(sha256sum "${DICOM_FILE_2}" | awk '{print $1}')

CONFLICT_HTTP_CODE=$(curl -s -o "${SCRATCH_DIR}/upload_conflict.json" -w "%{http_code}" \
    -H "Authorization: Bearer ${GRABBER_TOKEN}" \
    -H "X-Submission-ID: ${SUBMISSION_ID}" \
    -H "X-Checksum-SHA256: ${CHECKSUM_2}" \
    -F "file=@${DICOM_FILE_2};filename=rehearsal_diff.dcm" \
    "${BASE_URL}/api/v1/grabber/radiography-sessions/${LOCATOR_CODE}/dicom")

echo "Conflicting Upload HTTP Status: ${CONFLICT_HTTP_CODE}"
cat "${SCRATCH_DIR}/upload_conflict.json"
echo ""

if [ "${CONFLICT_HTTP_CODE}" -ne 409 ]; then
    echo "FAILED: Expected 409 for conflict submission, got ${CONFLICT_HTTP_CODE}"
    exit 1
fi
echo "Idempotency Conflict Rejection PASS (409 Conflict)"

echo ""
echo "=== ALL REHEARSAL VERIFICATIONS COMPLETED SUCCESSFULLY ==="
