# DDR Grabber Direct DICOM Ingestion Contract

**Target System:** `mhcs-core` (Madeena Health Care Services Core)  
**Client:** Madeena DDR Grabber / MPIPS Local Capture Engine  
**Contract Version:** 1.0  
**Governing Delivery Document:** `.agents/tasks/urgent-operator-field-operations.md` (Contract A, Execution Slice 3)  
**Status:** Authoritative Integration Contract  

---

## 1. Overview & Purpose

This contract defines the authenticated, idempotent boundary through which the Madeena Digital Direct Radiography (DDR) Grabber directly ingests Part 10 DICOM files into `mhcs-core`.

In this operational pattern:
1. The operator admits a member to radiography readiness (either via standard workflow or basic-examination bypass).
2. `mhcs-core` generates an active four-digit operational locator code (`0000`–`9999`) unique to the site and active shift.
3. The DDR Grabber resolves demographic metadata via `GET /api/v1/grabber/manifest/{code}` (or `POST /api/v1/grabber/manifest/lookup`).
4. The DDR Grabber / local capture engine generates a calibrated, enriched Part 10 DICOM file locally.
5. The Grabber uploads the DICOM file directly to `POST /api/v1/grabber/radiography-sessions/{code}/dicom` (or `POST /api/v1/grabber/dicom/upload`).
6. `mhcs-core` validates integrity, stores the object privately in `PrivateObjectStore`, binds it to the examination and queue admission, transitions queue state to `awaiting_ai` (or `completed`), invalidates the four-digit locator, and logs immutable audit and outbox events.

---

## 2. API Endpoints

### 2.1 Route Definitions

| Method | URI Path | Route Name | Description |
|---|---|---|---|
| `POST` | `/api/v1/grabber/radiography-sessions/{code}/dicom` | `api.grabber.radiography-session.dicom.upload` | Path-based locator code upload |
| `POST` | `/api/v1/grabber/dicom/upload` | `api.grabber.dicom.upload` | Body/header-based locator upload |
| `POST` | `/api/v1/grabber/dicom` | `api.grabber.dicom` | Alias for body/header locator upload |
| `POST` | `/api/v1/grabber/upload` | `api.grabber.upload` | Universal upload alias |

---

## 3. Authentication & Scoping

All requests must be authenticated using the Grabber client credentials established during site provisioning.

### Headers

- `Authorization: Bearer <grabber_api_token>` OR `X-Grabber-Token: <grabber_api_token>`: **Required**.
- `X-Grabber-ID: <grabber_id>`: **Optional**. Verified against authenticated client token if provided.
- `X-Site-ID: <operator_site_id>`: **Optional**. If provided, must match the permitted site assignment of the authenticated client. Cross-site uploads return `403 Forbidden`.
- `X-Shift-ID: <shift_schedule_id>`: **Optional**. If provided, must match an active (`open` or `in_progress`) shift for the permitted site. If omitted, the current active shift is auto-resolved.

---

## 4. Request Parameters & Body Format

### 4.1 Required Identifiers & Checksums

| Field / Header | Location | Type | Constraint | Description |
|---|---|---|---|---|
| `locator_code` | URL Path `{code}` OR Header `X-Locator-Code` OR Input `locator_code` | `string` | Exactly 4 digits (`^[0-9]{4}$`) | The active radiography session locator code. |
| `submission_id` | Header `X-Submission-ID` OR `X-Client-Submission-ID` OR `Idempotency-Key` OR Input `submission_id` | `string` | Non-empty, max 191 chars | Unique client-generated submission ID for idempotency deduplication. |
| `checksum` | Header `X-Checksum-SHA256` OR `X-SHA256` OR Input `checksum` | `string` | 64 hex characters (case-insensitive) | SHA-256 digest of the binary payload. Verified against computed digest. |
| `terminal_state` | Header `X-Terminal-State` OR Input `terminal_state` | `string` | Enum `["awaiting_ai", "completed"]` | Defaults to `"awaiting_ai"`. Target queue state after successful ingestion. |
| `patient_mrn` | Header `X-Patient-MRN` OR Input `medical_record_number` | `string` | Optional | If provided, verified against target session patient MRN. |

### 4.2 Payload Transfer Formats

Clients may upload the DICOM payload using either of two standard transfer formats:

#### Format A: Multipart Form Data
- Form field name: `file`, `dicom`, or `dicom_file`.
- Content-Type: `multipart/form-data`.
- Filename: e.g. `study.dcm`.

#### Format B: Raw Binary Stream
- Content-Type: `application/dicom` or `application/octet-stream`.
- Request body: Raw binary bytes.

---

## 5. Technical Validation Rules

1. **DICOM Magic Bytes:**
   - Minimum byte length: 132 bytes.
   - Part 10 DICOM files must contain `"DICM"` (`\x44\x49\x43\x4D`) at offset 128 (following the 128-byte preamble).
   - Alternatively, raw header-prefixed streams must start with `"DICM"` at offset 0.
   - Any upload failing this check is rejected with `422 Unprocessable Entity` (`invalid_dicom`).
2. **File Size Limit:**
   - Maximum upload size: Configured via `mhcs.upload.max_file_bytes` (default: 100 MB / 104,857,600 bytes).
   - Files exceeding this size are rejected with `422 Unprocessable Entity` (`file_too_large`).
3. **Integrity Checksum:**
   - Server computes `hash('sha256', $dicomBytes)`.
   - If client provides `X-Checksum-SHA256`, server verifies `hash_equals(client_checksum, computed_checksum)`.
   - Mismatches are rejected with `422 Unprocessable Entity` (`checksum_mismatch`).
4. **Session Eligibility:**
   - Admission must be in queue stage `xray`.
   - Admission state must be `waiting`, `called`, or `in_service`.
   - An admission already `completed` or `cancelled` cannot accept new uploads, returning `409 Conflict`.
5. **Anti-Enumeration Responses:**
   - Invalid 4-digit code format, non-existent code, cross-shift code, or expired code uniformly return `404 Not Found` with message: `"Radiography session not found."`.

---

## 6. Idempotency & Retry Guarantees

Idempotency is enforced using `IdempotencyStore` (`idempotent_consumptions` table) with consumer key `grabber.dicom.upload`:

1. **Initial Submission:**
   - On successful validation, the file is saved to `PrivateObjectStore`, database records are created, queue admission transitions to `awaiting_ai` (or `completed`), the locator code is marked `completed`, and HTTP `201 Created` is returned with `replayed: false`.
2. **Exact Retry (Same Submission ID & Same Payload):**
   - If the network drops or the Grabber client retries with the same `submission_id` and payload, `mhcs-core` suppresses duplicate execution:
     - No duplicate files are stored in `PrivateObjectStore`.
     - No duplicate `image_gateway_capture_sets` or `image_gateway_studies` are created.
     - No duplicate queue transitions or audit records occur.
     - HTTP `200 OK` is returned with `replayed: true` and the original study identifiers.
3. **Payload Mutation Conflict:**
   - If a client reuses an existing `submission_id` with a different checksum, different locator code, or different file, `mhcs-core` detects payload tampering/conflict and rejects with `409 Conflict`.
4. **Safe Failure / Retryable State:**
   - If an upload fails validation (e.g. checksum mismatch, corrupted magic bytes, network drop before database commit), the transaction rolls back, temporary storage is cleaned up, and the radiography session remains in its active `waiting` state with its 4-digit locator intact.

---

## 7. Response Formats

### 7.1 Success Response (`201 Created` or `200 OK` on Replay)

```json
{
  "status": "success",
  "study_id": "01918a24-9df2-70b1-8ef7-47ab59114f10",
  "display_reference": "DCM-A9K3F1M8",
  "admission_id": "01918a20-3ef0-7212-98ab-8c90fe123456",
  "locator_code": "4810",
  "terminal_state": "awaiting_ai",
  "checksum": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
  "bytes": 5242880,
  "replayed": false
}
```

### 7.2 Error Responses

| HTTP Status | Message | Cause / Condition |
|---|---|---|
| `401 Unauthorized` | `{"message": "Unauthenticated."}` | Missing or invalid API bearer token / `X-Grabber-Token`. |
| `403 Forbidden` | `{"message": "Forbidden."}` | Client deactivated, or `X-Site-ID` does not match client assignment. |
| `404 Not Found` | `{"message": "Radiography session not found."}` | Code not found, expired, wrong shift, or closed shift (anti-enumeration). |
| `409 Conflict` | `{"message": "Radiography session is not in an eligible state."}` | Admission is already completed or cancelled. |
| `409 Conflict` | `{"message": "Idempotency conflict for submission ID."}` | Same submission ID reused with differing payload. |
| `422 Unprocessable Entity` | `{"message": "Client submission identity is required."}` | Missing `X-Submission-ID` or `submission_id`. |
| `422 Unprocessable Entity` | `{"message": "Checksum does not match upload contents."}` | Client `X-Checksum-SHA256` differs from computed SHA-256. |
| `422 Unprocessable Entity` | `{"message": "Invalid DICOM file or magic bytes."}` | File < 132 bytes or missing `"DICM"` at byte 128. |
| `422 Unprocessable Entity` | `{"message": "File exceeds maximum allowed size."}` | File size exceeds configured upload limit. |
| `429 Too Many Requests` | `{"message": "Too many attempts. Please try again later."}` | Rate limit exceeded (> 60 req/min or > 10 failures/min). |

---

## 8. Preserved Lineage & Dual-Path Compatibility

This boundary is strictly additive and operates alongside the existing legacy NPZ pipeline:
- `mhcs-core` continues to support NPZ upload (`radiograph` + `gain` + `manifest`) via `ImageGatewayCaptureService` and async `ProcessCaptureSet` conversion.
- Direct DICOM uploads store study records directly in `image_gateway_studies` and `image_gateway_capture_sets`.
- Operator DICOM Viewer (`OperatorPortraitDicomViewer`), Doctor consultation tools, and administrative panels query both pathways seamlessly without discrimination.

---

## 9. Future Integration Rehearsal Fixture Guidance

For subsequent local cross-repository integration testing between `mhcs-core` and MPIPS:
- MPIPS fixtures generating synthetic DICOM files must ensure bytes 128..131 match `"DICM"`.
- MHCS Core test fixtures must provide an active `GrabberClient` record with an assigned site ID matching the test shift.
- The test harness should execute:
  1. Manifest resolution via `GET /api/v1/grabber/manifest/{code}`.
  2. MPIPS direct DICOM upload via `POST /api/v1/grabber/radiography-sessions/{code}/dicom`.
  3. Verification that DICOM viewer displays the study with matching display reference `DCM-XXXXXXXX`.
