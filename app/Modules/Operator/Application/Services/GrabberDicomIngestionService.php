<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\Models\GrabberClient;
use App\Modules\Operator\Domain\Models\RadiographySessionLocator;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Context\CorrelationId;
use App\Shared\Events\VersionedDomainEvent;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Infrastructure\Outbox\OutboxStore;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

final readonly class GrabberDicomIngestionService
{
    private const string CAPTURE_PURPOSE = 'image-gateway.capture.submit';

    private const string IDEMPOTENCY_CONSUMER = 'grabber.dicom.upload';

    private const int DICOM_HEADER_MIN_BYTES = 132;

    private const int DICOM_PREAMBLE_BYTES = 128;

    private const string DICOM_MAGIC_BYTES = 'DICM';

    public function __construct(
        private PrivateObjectStore $objects,
        private IdempotencyStore $idempotency,
        private AuditStore $audit,
        private OutboxStore $outbox,
        private Clock $clock,
        private RadiographySessionLocatorService $locators,
    ) {}

    /**
     * Ingest an authenticated DICOM file from the DDR Grabber.
     *
     * @return array{
     *     status: string,
     *     study_id: string,
     *     display_reference: string,
     *     admission_id: string,
     *     locator_code: string,
     *     terminal_state: string,
     *     checksum: string,
     *     bytes: int,
     *     replayed: bool
     * }
     */
    public function ingest(
        GrabberClient $client,
        string $locatorCode,
        string $submissionId,
        string $dicomBytes,
        ?string $requestedSiteId = null,
        ?string $requestedShiftId = null,
        ?string $clientChecksum = null,
        ?string $terminalState = 'awaiting_ai',
        ?string $patientMrn = null,
    ): array {
        $submissionId = trim($submissionId);
        if ($submissionId === '') {
            throw new OperatorException('missing_submission_id', 'Client submission identity is required.');
        }

        $locatorCode = trim($locatorCode);
        $permittedSiteId = (string) $client->operator_site_id;
        $now = $this->clock->now();

        // 1. Enforce site-level authorization independently of code correctness
        if ($requestedSiteId !== null && trim($requestedSiteId) !== '') {
            $requestedSiteId = trim($requestedSiteId);
            if ($requestedSiteId !== $permittedSiteId) {
                $this->auditFailure($client, $requestedSiteId, $requestedShiftId, $locatorCode, $submissionId, 'cross_site_denied');
                throw new OperatorException('cross_site_denied', 'Cross-site access denied.');
            }
        }

        // 2. Resolve and scope to active shift
        $shiftId = $this->resolveActiveShift($permittedSiteId, $requestedShiftId, $client, $locatorCode, $submissionId);

        // 3. Validate code format (anti-enumeration)
        if (preg_match('/^[0-9]{4}$/', $locatorCode) !== 1) {
            $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, $submissionId, 'invalid_code_format');
            throw new OperatorException('session_not_found', 'Radiography session not found.');
        }

        // 4. Validate file size
        $bytesCount = strlen($dicomBytes);
        $maxBytes = (int) config('mhcs.upload.max_file_bytes', 104857600);

        if ($bytesCount < self::DICOM_HEADER_MIN_BYTES) {
            $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, $submissionId, 'invalid_payload_size');
            throw new OperatorException('invalid_dicom', 'Invalid DICOM file or magic bytes.');
        }

        if ($bytesCount > $maxBytes) {
            $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, $submissionId, 'file_too_large');
            throw new OperatorException('file_too_large', 'File exceeds maximum allowed size.');
        }

        // 5. Validate DICOM magic bytes / signature
        $hasPart10Magic = substr($dicomBytes, self::DICOM_PREAMBLE_BYTES, 4) === self::DICOM_MAGIC_BYTES;
        $hasHeaderMagic = substr($dicomBytes, 0, 4) === self::DICOM_MAGIC_BYTES;

        if (! $hasPart10Magic && ! $hasHeaderMagic) {
            $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, $submissionId, 'invalid_header_signature');
            throw new OperatorException('invalid_dicom', 'Invalid DICOM file or magic bytes.');
        }

        // 6. Validate SHA-256 integrity
        $computedChecksum = hash('sha256', $dicomBytes);
        if ($clientChecksum !== null && trim($clientChecksum) !== '') {
            $normalizedExpected = strtolower(trim($clientChecksum));
            if (! hash_equals($normalizedExpected, $computedChecksum)) {
                $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, $submissionId, 'checksum_mismatch');
                throw new OperatorException('checksum_mismatch', 'Checksum does not match upload contents.');
            }
        }

        // 7. Validate terminal state parameter
        $targetTerminalState = in_array($terminalState, ['completed', 'awaiting_ai'], true) ? $terminalState : 'awaiting_ai';

        // 8. Idempotency barrier
        $idempotencyPayload = [
            'submission_id' => $submissionId,
            'locator_code' => $locatorCode,
            'site_id' => $permittedSiteId,
            'shift_id' => $shiftId,
            'checksum' => $computedChecksum,
            'bytes' => $bytesCount,
            'terminal_state' => $targetTerminalState,
        ];

        $outcome = $this->idempotency->run(
            $submissionId,
            self::IDEMPOTENCY_CONSUMER,
            $idempotencyPayload,
            function () use (
                $client,
                $locatorCode,
                $permittedSiteId,
                $shiftId,
                $submissionId,
                $dicomBytes,
                $targetTerminalState,
                $patientMrn,
            ): array {
                // Find active session locator
                $locator = RadiographySessionLocator::query()
                    ->where('operator_site_id', $permittedSiteId)
                    ->where('member_schedule_id', $shiftId)
                    ->where('locator_code', $locatorCode)
                    ->where('status', 'active')
                    ->first();

                if ($locator === null) {
                    $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, $submissionId, 'session_not_found');
                    throw new OperatorException('session_not_found', 'Radiography session not found.');
                }

                // Query and lock admission
                $admission = DB::table('operator_queue_admissions as admissions')
                    ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
                    ->join('bookings', 'bookings.id', '=', 'tickets.booking_id')
                    ->join('members', 'members.id', '=', 'bookings.member_id')
                    ->where('admissions.id', $locator->operator_queue_admission_id)
                    ->select([
                        'admissions.id as admission_id',
                        'admissions.stage',
                        'admissions.state',
                        'admissions.operator_profile_id as admission_operator_profile_id',
                        'tickets.operator_profile_id as ticket_operator_profile_id',
                        'tickets.booking_id',
                        'members.medical_record_number',
                    ])
                    ->first();

                if ($admission === null
                    || $admission->stage !== 'xray'
                    || ! in_array($admission->state, ['waiting', 'called', 'in_service'], true)) {
                    $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, $submissionId, 'session_ineligible');
                    throw new OperatorException('session_conflict', 'Radiography session is not in an eligible state.');
                }

                // Patient MRN binding check if provided
                if ($patientMrn !== null && trim($patientMrn) !== '') {
                    if (strcasecmp(trim((string) $admission->medical_record_number), trim($patientMrn)) !== 0) {
                        $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, $submissionId, 'identifier_mismatch');
                        throw new OperatorException('patient_mismatch', 'Patient MRN does not match active session.');
                    }
                }

                // Store in PrivateObjectStore
                $now = $this->clock->now();
                $context = new AuthenticatedContext(
                    actorId: LocalId::fromString((string) $client->id),
                    operationId: new CorrelationId($submissionId),
                    roles: ['grabber'],
                    permissions: ['grabber.dicom.upload'],
                    siteId: LocalId::fromString($permittedSiteId),
                    purpose: self::CAPTURE_PURPOSE,
                );

                $studyObject = $this->objects->put($dicomBytes, $context, self::CAPTURE_PURPOSE);

                try {
                    return DB::transaction(function () use (
                        $admission,
                        $client,
                        $permittedSiteId,
                        $shiftId,
                        $locatorCode,
                        $submissionId,
                        $studyObject,
                        $targetTerminalState,
                        $now,
                    ): array {
                        $admissionRow = DB::table('operator_queue_admissions')
                            ->where('id', $admission->admission_id)
                            ->lockForUpdate()
                            ->first();

                        if ($admissionRow === null || ! in_array($admissionRow->state, ['waiting', 'called', 'in_service'], true)) {
                            throw new OperatorException('session_conflict', 'Radiography session is not in an eligible state.');
                        }

                        $operatorProfileId = (string) ($admissionRow->operator_profile_id ?? $admission->ticket_operator_profile_id);

                        // Ensure or find capture set
                        $captureSet = DB::table('image_gateway_capture_sets')
                            ->where('admission_id', $admission->admission_id)
                            ->lockForUpdate()
                            ->first();

                        if ($captureSet === null) {
                            $captureId = (string) Str::uuid();
                            DB::table('image_gateway_capture_sets')->insert([
                                'id' => $captureId,
                                'submission_id' => $submissionId,
                                'admission_id' => $admission->admission_id,
                                'booking_id' => $admission->booking_id,
                                'member_schedule_id' => $shiftId,
                                'operator_site_id' => $permittedSiteId,
                                'operator_profile_id' => $operatorProfileId,
                                'radiograph_count' => 1,
                                'status' => 'accepted',
                                'accepted_at' => $now,
                                'processing_status' => 'completed',
                                'dicom_status' => 'success',
                                'completed_at' => $now,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        } else {
                            $captureId = (string) $captureSet->id;
                            DB::table('image_gateway_capture_sets')
                                ->where('id', $captureId)
                                ->update([
                                    'status' => 'accepted',
                                    'accepted_at' => $captureSet->accepted_at ?? $now,
                                    'processing_status' => 'completed',
                                    'dicom_status' => 'success',
                                    'completed_at' => $now,
                                    'updated_at' => $now,
                                ]);
                        }

                        // Generate unique display reference for the study
                        $displayReference = $this->generateUniqueStudyReference();
                        $studyId = (string) Str::uuid();

                        // Derive deterministic UIDs for the study
                        $uids = $this->deriveUids($studyId);

                        // Insert study record
                        DB::table('image_gateway_studies')->insert([
                            'id' => $studyId,
                            'capture_set_id' => $captureId,
                            'display_reference' => $displayReference,
                            'object_key' => (string) $studyObject->key,
                            'checksum' => $studyObject->checksum,
                            'bytes' => $studyObject->bytes,
                            'format' => 'application/dicom',
                            'filename' => $displayReference.'.dcm',
                            'study_instance_uid' => $uids['study'],
                            'series_instance_uid' => $uids['series'],
                            'sop_instance_uid' => $uids['sop'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        // Perform terminal queue admission transition
                        $fromState = (string) $admissionRow->state;
                        DB::table('operator_queue_admissions')
                            ->where('id', $admission->admission_id)
                            ->update([
                                'state' => $targetTerminalState,
                                'operator_profile_id' => null,
                                'claimed_at' => null,
                                'updated_at' => $now,
                            ]);

                        // Insert queue admission history
                        DB::table('operator_queue_admission_history')->insert([
                            'id' => (string) Str::uuid(),
                            'operator_queue_admission_id' => $admission->admission_id,
                            'operator_profile_id' => $operatorProfileId,
                            'event_type' => 'dicom_ingested',
                            'from_state' => $fromState,
                            'to_state' => $targetTerminalState,
                            'operation_id' => $submissionId,
                            'occurred_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        // Invalidate active session locator
                        $this->locators->markCompleted($admission->admission_id, 'dicom_uploaded');

                        // Audit successful ingestion
                        $metadata = [
                            'grabber_id' => (string) $client->grabber_id,
                            'operator_site_id' => $permittedSiteId,
                            'schedule_id' => $shiftId,
                            'code' => $locatorCode,
                            'admission_id' => (string) $admission->admission_id,
                            'study_id' => $studyId,
                            'capture_id' => $captureId,
                            'display_reference' => $displayReference,
                            'checksum' => $studyObject->checksum,
                            'bytes' => $studyObject->bytes,
                            'terminal_state' => $targetTerminalState,
                        ];

                        $this->audit->append(new AuditEvent(
                            eventId: (string) Str::uuid(),
                            eventVersion: 1,
                            actorId: LocalId::fromString((string) $client->id),
                            sessionId: null,
                            roles: ['grabber'],
                            permissions: ['grabber.dicom.upload'],
                            siteId: LocalId::fromString($permittedSiteId),
                            caseId: null,
                            targetType: 'queue-admission',
                            targetId: (string) $admission->admission_id,
                            action: 'grabber.dicom.uploaded',
                            previousStateDigest: null,
                            newStateDigest: null,
                            reason: null,
                            occurredAt: $now,
                            recordedAt: $now,
                            correlationId: $submissionId,
                            source: 'grabber',
                            outcome: 'success',
                            metadata: $metadata,
                        ));

                        $this->outbox->record(new VersionedDomainEvent(
                            LocalId::fromString((string) Str::uuid()),
                            'grabber-dicom-uploaded',
                            1,
                            $now,
                            $metadata,
                            LocalId::fromString($studyId),
                            new CorrelationId($submissionId),
                        ));

                        return [
                            'status' => 'success',
                            'study_id' => $studyId,
                            'display_reference' => $displayReference,
                            'admission_id' => (string) $admission->admission_id,
                            'locator_code' => $locatorCode,
                            'terminal_state' => $targetTerminalState,
                            'checksum' => $studyObject->checksum,
                            'bytes' => $studyObject->bytes,
                        ];
                    });
                } catch (Throwable $exception) {
                    $this->objects->delete($studyObject);
                    throw $exception;
                }
            }
        );

        $result = $outcome->result;
        $result['replayed'] = $outcome->status === 'replayed';

        return $result;
    }

    private function resolveActiveShift(
        string $siteId,
        ?string $requestedShiftId,
        GrabberClient $client,
        string $locatorCode,
        string $submissionId,
    ): string {
        if ($requestedShiftId !== null && trim($requestedShiftId) !== '') {
            $requestedShiftId = trim($requestedShiftId);

            $shift = DB::table('shift_schedules as schedules')
                ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
                ->join('operator_sites as sites', 'sites.operator_site_id', '=', 'member_sites.operator_site_id')
                ->where('schedules.id', $requestedShiftId)
                ->where('sites.id', $siteId)
                ->select(['schedules.id', 'schedules.status', 'schedules.ends_at'])
                ->first();

            if ($shift === null) {
                $this->auditFailure($client, $siteId, $requestedShiftId, $locatorCode, $submissionId, 'cross_shift_denied');
                throw new OperatorException('session_not_found', 'Radiography session not found.');
            }

            if (! in_array($shift->status, ['open', 'in_progress'], true)) {
                $this->auditFailure($client, $siteId, $requestedShiftId, $locatorCode, $submissionId, 'shift_closed');
                throw new OperatorException('session_not_found', 'Radiography session not found.');
            }

            return (string) $shift->id;
        }

        $activeShift = DB::table('shift_schedules as schedules')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->join('operator_sites as sites', 'sites.operator_site_id', '=', 'member_sites.operator_site_id')
            ->where('sites.id', $siteId)
            ->whereIn('schedules.status', ['open', 'in_progress'])
            ->orderBy('schedules.starts_at', 'desc')
            ->select('schedules.id')
            ->first();

        if ($activeShift === null) {
            $this->auditFailure($client, $siteId, null, $locatorCode, $submissionId, 'no_active_shift');
            throw new OperatorException('session_not_found', 'Radiography session not found.');
        }

        return (string) $activeShift->id;
    }

    private function generateUniqueStudyReference(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = 'DCM-'.Str::upper(Str::random(8));
            if (! DB::table('image_gateway_studies')->where('display_reference', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'DCM-'.Str::upper(Str::random(8));
    }

    /**
     * @return array{study: string, series: string, sop: string}
     */
    private function deriveUids(string $studyId): array
    {
        $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');

        return [
            'study' => '2.25.'.Uuid::uuid5($namespace, 'grabber:study:'.$studyId)->getInteger()->toString(),
            'series' => '2.25.'.Uuid::uuid5($namespace, 'grabber:series:'.$studyId)->getInteger()->toString(),
            'sop' => '2.25.'.Uuid::uuid5($namespace, 'grabber:sop:'.$studyId)->getInteger()->toString(),
        ];
    }

    private function auditFailure(
        GrabberClient $client,
        string $siteId,
        ?string $shiftId,
        string $locatorCode,
        string $submissionId,
        string $reason,
    ): void {
        $now = $this->clock->now();

        $metadata = [
            'grabber_id' => (string) $client->grabber_id,
            'operator_site_id' => $siteId,
            'code' => $locatorCode,
            'submission_id' => $submissionId,
        ];
        if ($shiftId !== null) {
            $metadata['schedule_id'] = $shiftId;
        }

        $this->audit->append(new AuditEvent(
            eventId: (string) Str::uuid(),
            eventVersion: 1,
            actorId: LocalId::fromString((string) $client->id),
            sessionId: null,
            roles: ['grabber'],
            permissions: ['grabber.dicom.upload'],
            siteId: LocalId::fromString($siteId),
            caseId: null,
            targetType: 'queue-admission',
            targetId: $locatorCode,
            action: 'grabber.dicom.upload_failed',
            previousStateDigest: null,
            newStateDigest: null,
            reason: $reason,
            occurredAt: $now,
            recordedAt: $now,
            correlationId: $submissionId,
            source: 'grabber',
            outcome: 'failure',
            metadata: $metadata,
        ));
    }
}
