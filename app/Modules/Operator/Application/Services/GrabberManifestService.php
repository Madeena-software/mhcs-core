<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\Models\GrabberClient;
use App\Modules\Operator\Domain\Models\RadiographySessionLocator;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Identity\LocalId;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class GrabberManifestService
{
    public function __construct(
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    /**
     * Resolve the minimal DICOM manifest for an active radiography session locator code.
     *
     * @return array{
     *     examination: array{study_description: string},
     *     patient: array{medical_record_number: string, name: string, sex: string, birth_date: string},
     *     capture: array{detector_type: string, body_part_examined: string, laterality: string, projection: string}
     * }
     */
    public function resolve(
        GrabberClient $client,
        string $locatorCode,
        ?string $requestedSiteId = null,
        ?string $requestedShiftId = null,
    ): array {
        $locatorCode = trim($locatorCode);
        $permittedSiteId = (string) $client->operator_site_id;
        $now = $this->clock->now();

        // 1. Enforce site-level authorization independently of code correctness
        if ($requestedSiteId !== null && trim($requestedSiteId) !== '') {
            $requestedSiteId = trim($requestedSiteId);
            if ($requestedSiteId !== $permittedSiteId) {
                $this->auditFailure($client, $requestedSiteId, $requestedShiftId, $locatorCode, 'cross_site_denied');
                throw new OperatorException('cross_site_denied', 'Cross-site access denied.');
            }
        }

        // 2. Resolve and scope to active shift
        $shiftId = $this->resolveActiveShift($permittedSiteId, $requestedShiftId, $client, $locatorCode);

        // 3. Validate code format (anti-enumeration)
        if (preg_match('/^[0-9]{4}$/', $locatorCode) !== 1) {
            $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, 'invalid_code_format');
            throw new OperatorException('session_not_found', 'Radiography session not found.');
        }

        // 4. Look up active session locator scoped to site and shift
        $locator = RadiographySessionLocator::query()
            ->where('operator_site_id', $permittedSiteId)
            ->where('member_schedule_id', $shiftId)
            ->where('locator_code', $locatorCode)
            ->where('status', 'active')
            ->first();

        if ($locator === null) {
            $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, 'session_not_found');
            throw new OperatorException('session_not_found', 'Radiography session not found.');
        }

        // 5. Verify eligible radiography-session state
        $admission = DB::table('operator_queue_admissions')
            ->where('id', $locator->operator_queue_admission_id)
            ->first();

        if ($admission === null
            || $admission->stage !== 'xray'
            || ! in_array($admission->state, ['waiting', 'called', 'in_service'], true)) {
            $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, 'session_ineligible');
            throw new OperatorException('session_not_found', 'Radiography session not found.');
        }

        // 6. Query patient and examination details
        $source = DB::table('operator_queue_admissions as admissions')
            ->join('operator_paper_tickets as tickets', 'tickets.id', '=', 'admissions.operator_paper_ticket_id')
            ->join('bookings', 'bookings.id', '=', 'tickets.booking_id')
            ->join('members', 'members.id', '=', 'bookings.member_id')
            ->leftJoin('service_offerings', 'service_offerings.id', '=', 'bookings.service_offering_id')
            ->where('admissions.id', $admission->id)
            ->select([
                'members.medical_record_number',
                'members.name as member_name',
                'members.administrative_gender',
                'members.birth_date',
                'service_offerings.name as study_description',
                'bookings.service_code_snapshot',
            ])
            ->first();

        if ($source === null) {
            $this->auditFailure($client, $permittedSiteId, $shiftId, $locatorCode, 'member_data_missing');
            throw new OperatorException('session_not_found', 'Radiography session not found.');
        }

        $sex = match (strtolower((string) $source->administrative_gender)) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            'other' => 'other',
            default => 'unknown',
        };

        $studyDescription = trim((string) ($source->study_description ?? $source->service_code_snapshot ?? ''));
        if ($studyDescription === '') {
            $studyDescription = 'CHEST RADIOGRAPH';
        }

        // Check if capture set has custom metadata stored
        $captureMetadata = null;
        $captureSet = DB::table('image_gateway_capture_sets')->where('admission_id', $admission->id)->first();
        if ($captureSet !== null && is_string($captureSet->capture_metadata ?? null)) {
            $decoded = json_decode($captureSet->capture_metadata, true);
            if (is_array($decoded)) {
                $captureMetadata = $decoded;
            }
        }

        $detectorType = (string) ($captureMetadata['capture']['detector_type'] ?? 'THORAX');
        $bodyPart = (string) ($captureMetadata['capture']['body_part_examined'] ?? 'CHEST');
        $laterality = (string) ($captureMetadata['capture']['laterality'] ?? 'U');
        $projection = (string) ($captureMetadata['capture']['projection'] ?? 'PA');

        // strictly build minimal manifest according to authoritative manifest example
        $manifest = [
            'examination' => [
                'study_description' => $studyDescription,
            ],
            'patient' => [
                'medical_record_number' => (string) $source->medical_record_number,
                'name' => (string) $source->member_name,
                'sex' => $sex,
                'birth_date' => (string) $source->birth_date,
            ],
            'capture' => [
                'detector_type' => $detectorType,
                'body_part_examined' => $bodyPart,
                'laterality' => $laterality,
                'projection' => $projection,
            ],
        ];

        // Audit successful resolution (strict privacy: no NIK, no phone, no raw secrets)
        $this->audit->append(new AuditEvent(
            eventId: (string) Str::uuid(),
            eventVersion: 1,
            actorId: LocalId::fromString((string) $client->id),
            sessionId: null,
            roles: ['grabber'],
            permissions: ['grabber.manifest.read'],
            siteId: LocalId::fromString($permittedSiteId),
            caseId: null,
            targetType: 'queue-admission',
            targetId: (string) $admission->id,
            action: 'grabber.session.resolved',
            previousStateDigest: null,
            newStateDigest: null,
            reason: null,
            occurredAt: $now,
            recordedAt: $now,
            correlationId: null,
            source: 'grabber',
            outcome: 'success',
            metadata: [
                'grabber_id' => (string) $client->grabber_id,
                'operator_site_id' => $permittedSiteId,
                'schedule_id' => $shiftId,
                'code' => $locatorCode,
                'admission_id' => (string) $admission->id,
            ],
        ));

        return $manifest;
    }

    private function resolveActiveShift(
        string $siteId,
        ?string $requestedShiftId,
        GrabberClient $client,
        string $locatorCode,
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
                // Cross-shift or non-existent shift for this site
                $this->auditFailure($client, $siteId, $requestedShiftId, $locatorCode, 'cross_shift_denied');
                throw new OperatorException('session_not_found', 'Radiography session not found.');
            }

            if (! in_array($shift->status, ['open', 'in_progress'], true)) {
                $this->auditFailure($client, $siteId, $requestedShiftId, $locatorCode, 'shift_closed');
                throw new OperatorException('session_not_found', 'Radiography session not found.');
            }

            return (string) $shift->id;
        }

        // Auto-resolve active shift for the site
        $activeShift = DB::table('shift_schedules as schedules')
            ->join('examination_site_refs as member_sites', 'member_sites.id', '=', 'schedules.examination_site_id')
            ->join('operator_sites as sites', 'sites.operator_site_id', '=', 'member_sites.operator_site_id')
            ->where('sites.id', $siteId)
            ->whereIn('schedules.status', ['open', 'in_progress'])
            ->orderBy('schedules.starts_at', 'desc')
            ->select('schedules.id')
            ->first();

        if ($activeShift === null) {
            $this->auditFailure($client, $siteId, null, $locatorCode, 'no_active_shift');
            throw new OperatorException('session_not_found', 'Radiography session not found.');
        }

        return (string) $activeShift->id;
    }

    private function auditFailure(
        GrabberClient $client,
        string $siteId,
        ?string $shiftId,
        string $locatorCode,
        string $reason,
    ): void {
        $now = $this->clock->now();

        $metadata = [
            'grabber_id' => (string) $client->grabber_id,
            'operator_site_id' => $siteId,
            'code' => $locatorCode,
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
            permissions: ['grabber.manifest.read'],
            siteId: LocalId::fromString($siteId),
            caseId: null,
            targetType: 'queue-admission',
            targetId: null,
            action: 'grabber.session.failed',
            previousStateDigest: null,
            newStateDigest: null,
            reason: $reason,
            occurredAt: $now,
            recordedAt: $now,
            correlationId: null,
            source: 'grabber',
            outcome: 'failure',
            metadata: $metadata,
        ));
    }
}
