<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Operator\Domain\Models\ConsentVisitConfirmation;
use App\Modules\Operator\Domain\Models\MemberMasterConsent;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Infrastructure\Idempotency\IdempotencyStore;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OperatorReusableConsentService
{
    public const DEFAULT_SCOPE = 'radiography_screening';

    public const APPROVED_FORMS = [
        'Informed Consent' => 'V1',
        'Master Screening Consent' => 'V1',
    ];

    private const UPLOAD_PURPOSE = 'operator.paper-consent.upload';

    private const UPLOAD_FORMATS = ['image/jpeg', 'image/png', 'application/pdf'];

    public function __construct(
        private OperatorAuthorization $authorization,
        private OperatorShiftAssignmentService $assignments,
        private AuditStore $audit,
        private IdempotencyStore $idempotency,
        private Clock $clock,
        private ?PrivateObjectStore $objects = null,
    ) {}

    public function getActiveMasterConsent(string $memberId, ?string $screeningScope = null): ?MemberMasterConsent
    {
        $query = MemberMasterConsent::query()
            ->where('member_id', $memberId)
            ->where('status', 'active');

        if ($screeningScope !== null && trim($screeningScope) !== '') {
            $query->where('screening_scope', trim($screeningScope));
        }

        return $query->orderByDesc('consent_version')->first();
    }

    public function getLatestMasterConsent(string $memberId, ?string $screeningScope = null): ?MemberMasterConsent
    {
        $query = MemberMasterConsent::query()
            ->where('member_id', $memberId);

        if ($screeningScope !== null && trim($screeningScope) !== '') {
            $query->where('screening_scope', trim($screeningScope));
        }

        return $query->orderByDesc('consent_version')->first();
    }

    /** @return array<string, mixed> */
    public function viewConsentState(string $caseId): array
    {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $booking = DB::table('bookings')->where('id', $case->booking_id)->first();
        if ($booking === null) {
            throw new OperatorException('consent_unavailable', 'The booking is unavailable.');
        }

        $memberId = (string) $booking->member_id;
        $activeConsent = $this->getActiveMasterConsent($memberId);
        $latestConsent = $this->getLatestMasterConsent($memberId);
        $visitConfirmation = DB::table('consent_visit_confirmations')
            ->where('booking_id', $case->booking_id)
            ->first();
        $legacyConsent = DB::table('examination_consents')
            ->where('booking_id', $case->booking_id)
            ->first();

        return [
            'case_id' => $caseId,
            'booking_id' => (string) $case->booking_id,
            'member_id' => $memberId,
            'active_master_consent' => $activeConsent,
            'latest_master_consent' => $latestConsent,
            'has_active_master_consent' => $activeConsent !== null,
            'is_withdrawn' => $latestConsent !== null && $latestConsent->isWithdrawn(),
            'visit_confirmed' => $visitConfirmation !== null || ($legacyConsent !== null && $legacyConsent->status === 'confirmed'),
            'legacy_consent' => $legacyConsent,
        ];
    }

    /** @return array<string, mixed> */
    public function recordMasterConsent(
        string $caseId,
        string $signerType,
        string $signedAt,
        string $operationId,
        ?UploadedFile $scan = null,
        string $screeningScope = self::DEFAULT_SCOPE,
        string $formName = 'Informed Consent',
        string $formVersion = 'V1',
        ?string $legacyConsentId = null,
    ): array {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $profileId = (string) $identity['profile']->getKey();
        $scheduleId = (string) $case->member_schedule_id;

        if (! $this->assignments->isAssigned($profileId, $scheduleId, $site->operator_site_id)) {
            throw new OperatorException('consent_denied', 'The Operator is not assigned to this shift.');
        }

        if (! array_key_exists($formName, self::APPROVED_FORMS) || self::APPROVED_FORMS[$formName] !== $formVersion) {
            throw new OperatorException('consent_invalid', 'Only approved consent forms and versions are supported.');
        }

        $booking = DB::table('bookings')->where('id', $case->booking_id)->first();
        if ($booking === null) {
            throw new OperatorException('consent_unavailable', 'The booking is unavailable.');
        }

        $memberId = (string) $booking->member_id;
        $operationId = trim($operationId);
        if (! Str::isUuid($operationId)) {
            throw new OperatorException('consent_invalid', 'A valid operation ID is required.');
        }

        $signedInstant = $this->instant($signedAt);
        $now = $this->clock->now();

        // Resolve scan evidence
        $scanObjectKey = null;
        $scanChecksum = null;
        $scanBytes = null;
        $scanFormat = null;

        if ($legacyConsentId !== null) {
            $legacyConsent = DB::table('examination_consents')->where('id', $legacyConsentId)->first();
            if ($legacyConsent !== null) {
                $scanObjectKey = $legacyConsent->private_scan_object_key;
                $scanChecksum = $legacyConsent->private_scan_checksum;
                $scanBytes = $legacyConsent->private_scan_bytes !== null ? (int) $legacyConsent->private_scan_bytes : null;
                $scanFormat = $legacyConsent->private_scan_format;
            }
        } elseif ($scan !== null) {
            $upload = $this->validatedUpload($scan);
            $scanChecksum = $upload['checksum'];
            $scanBytes = $upload['bytes'];
            $scanFormat = $upload['format'];

            if ($this->objects !== null) {
                $storedObject = $this->objects->put(
                    $upload['contents'],
                    $identity['context']->forPurpose(self::UPLOAD_PURPOSE),
                    self::UPLOAD_PURPOSE,
                );
                $scanObjectKey = (string) $storedObject->key;
            }
        }

        $payload = [
            'case_id' => $caseId,
            'member_id' => $memberId,
            'booking_id' => (string) $case->booking_id,
            'operator_site_id' => $site->operator_site_id,
            'screening_scope' => $screeningScope,
            'signer_type' => $signerType,
            'form_name' => $formName,
            'form_version' => $formVersion,
            'signed_at' => $signedInstant->format(DATE_ATOM),
            'has_scan' => $scanObjectKey !== null,
        ];

        $outcome = $this->idempotency->run(
            'master-consent:'.$operationId,
            'operator.master-consent.record',
            $payload,
            function () use (
                $identity, $site, $case, $profileId, $booking, $memberId, $signerType, $signedInstant,
                $operationId, $screeningScope, $formName, $formVersion, $legacyConsentId,
                $scanObjectKey, $scanChecksum, $scanBytes, $scanFormat, $now
            ): array {
                return DB::transaction(function () use (
                    $identity, $site, $case, $profileId, $booking, $memberId, $signerType, $signedInstant,
                    $operationId, $screeningScope, $formName, $formVersion, $legacyConsentId,
                    $scanObjectKey, $scanChecksum, $scanBytes, $scanFormat, $now
                ): array {
                    // Supersede any existing active consent for this member and scope
                    $existingActive = MemberMasterConsent::query()
                        ->where('member_id', $memberId)
                        ->where('screening_scope', $screeningScope)
                        ->where('status', 'active')
                        ->lockForUpdate()
                        ->get();

                    foreach ($existingActive as $active) {
                        $active->update(['status' => 'superseded']);
                    }

                    $latestVersion = (int) MemberMasterConsent::query()
                        ->where('member_id', $memberId)
                        ->max('consent_version');
                    $newVersion = $latestVersion + 1;

                    $masterConsentId = (string) Str::uuid();
                    MemberMasterConsent::query()->create([
                        'id' => $masterConsentId,
                        'member_id' => $memberId,
                        'examination_consent_id' => $legacyConsentId,
                        'consent_version' => $newVersion,
                        'form_name' => $formName,
                        'form_version' => $formVersion,
                        'screening_scope' => $screeningScope,
                        'signer_type' => $signerType,
                        'signer_member_id' => $memberId,
                        'signed_at' => $signedInstant,
                        'private_scan_object_key' => $scanObjectKey,
                        'private_scan_checksum' => $scanChecksum,
                        'private_scan_bytes' => $scanBytes,
                        'private_scan_format' => $scanFormat,
                        'status' => 'active',
                        'withdrawn_at' => null,
                        'withdrawn_reason' => null,
                        'withdrawn_by_operator_id' => null,
                        'created_by_operator_id' => $profileId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    // Per-visit confirmation: update if existing for booking (new version recorded during same booking), or create
                    $existingConfirmation = ConsentVisitConfirmation::query()
                        ->where('booking_id', (string) $case->booking_id)
                        ->lockForUpdate()
                        ->first();

                    if ($existingConfirmation !== null) {
                        $existingConfirmation->update([
                            'member_master_consent_id' => $masterConsentId,
                            'confirmed_by_operator_id' => $profileId,
                            'confirmed_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $visitConfirmationId = (string) $existingConfirmation->id;
                    } else {
                        $visitConfirmationId = (string) Str::uuid();
                        ConsentVisitConfirmation::query()->create([
                            'id' => $visitConfirmationId,
                            'booking_id' => (string) $case->booking_id,
                            'member_id' => $memberId,
                            'member_master_consent_id' => $masterConsentId,
                            'examination_site_id' => (string) $booking->examination_site_id_snapshot,
                            'operator_site_id' => (string) $site->operator_site_id,
                            'confirmed_by_operator_id' => $profileId,
                            'confirmed_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    // Backward-compatible examination_consents entry if not already created by legacy confirm
                    if ($legacyConsentId === null) {
                        $this->upsertExaminationConsent(
                            bookingId: (string) $case->booking_id,
                            memberId: $memberId,
                            examinationSiteId: (string) $booking->examination_site_id_snapshot,
                            operatorSiteId: (string) $site->operator_site_id,
                            signerType: $signerType,
                            signedAt: $signedInstant,
                            operatorProfileId: $profileId,
                            operationId: $operationId,
                            scanObjectKey: $scanObjectKey,
                            scanChecksum: $scanChecksum,
                            scanBytes: $scanBytes,
                            scanFormat: $scanFormat,
                            now: $now,
                        );
                    }

                    $this->audit->append(AuditEvent::fromContext(
                        $identity['context'],
                        'operator.master-consent.recorded',
                        'operator',
                        'success',
                        $now,
                        MemberMasterConsent::class,
                        $masterConsentId,
                        metadata: [
                            'consent_version' => $newVersion,
                            'operator_site_id' => $site->operator_site_id,
                            'booking_id' => (string) $case->booking_id,
                            'form_name' => $formName,
                            'form_version' => $formVersion,
                            'has_private_scan' => $scanObjectKey !== null,
                        ],
                    ));

                    return [
                        'master_consent_id' => $masterConsentId,
                        'consent_version' => $newVersion,
                        'visit_confirmation_id' => $visitConfirmationId,
                        'status' => 'confirmed',
                    ];
                });
            }
        );

        return (array) $outcome->result;
    }

    /** @return array<string, mixed> */
    public function confirmVisit(string $caseId, string $operationId, ?string $screeningScope = null): array
    {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $profileId = (string) $identity['profile']->getKey();
        $scheduleId = (string) $case->member_schedule_id;

        if (! $this->assignments->isAssigned($profileId, $scheduleId, $site->operator_site_id)) {
            throw new OperatorException('consent_denied', 'The Operator is not assigned to this shift.');
        }

        $booking = DB::table('bookings')->where('id', $case->booking_id)->first();
        if ($booking === null) {
            throw new OperatorException('consent_unavailable', 'The booking is unavailable.');
        }

        $memberId = (string) $booking->member_id;
        $latestConsent = $this->getLatestMasterConsent($memberId, $screeningScope);
        if ($latestConsent === null) {
            throw new OperatorException('consent_missing', 'No prior informed consent found. A new master consent must be recorded.');
        }
        if ($latestConsent->isWithdrawn()) {
            throw new OperatorException('consent_withdrawn', 'Informed consent was withdrawn. A new signed consent is required before proceeding.');
        }
        if (! $latestConsent->isActive()) {
            throw new OperatorException('consent_missing', 'The master informed consent is no longer active. A new master consent must be recorded.');
        }

        $operationId = trim($operationId);
        if (! Str::isUuid($operationId)) {
            throw new OperatorException('consent_invalid', 'A valid operation ID is required.');
        }

        $now = $this->clock->now();

        $payload = [
            'case_id' => $caseId,
            'booking_id' => (string) $case->booking_id,
            'master_consent_id' => (string) $latestConsent->id,
            'operator_site_id' => $site->operator_site_id,
        ];

        $outcome = $this->idempotency->run(
            'visit-confirm:'.$operationId,
            'operator.consent.visit-confirm',
            $payload,
            function () use ($identity, $site, $case, $profileId, $booking, $memberId, $latestConsent, $operationId, $now): array {
                return DB::transaction(function () use ($identity, $site, $case, $profileId, $booking, $memberId, $latestConsent, $operationId, $now): array {
                    $existing = ConsentVisitConfirmation::query()
                        ->where('booking_id', (string) $case->booking_id)
                        ->first();

                    if ($existing !== null) {
                        $existing->forceFill([
                            'member_master_consent_id' => (string) $latestConsent->id,
                            'operator_site_id' => (string) $site->operator_site_id,
                            'confirmed_by_operator_id' => $profileId,
                            'confirmed_at' => $now,
                            'updated_at' => $now,
                        ])->save();

                        $this->upsertExaminationConsent(
                            bookingId: (string) $case->booking_id,
                            memberId: $memberId,
                            examinationSiteId: (string) $booking->examination_site_id_snapshot,
                            operatorSiteId: (string) $site->operator_site_id,
                            signerType: (string) $latestConsent->signer_type,
                            signedAt: $latestConsent->signed_at,
                            operatorProfileId: $profileId,
                            operationId: $operationId,
                            scanObjectKey: $latestConsent->private_scan_object_key,
                            scanChecksum: $latestConsent->private_scan_checksum,
                            scanBytes: $latestConsent->private_scan_bytes !== null ? (int) $latestConsent->private_scan_bytes : null,
                            scanFormat: $latestConsent->private_scan_format,
                            now: $now,
                        );

                        return [
                            'visit_confirmation_id' => (string) $existing->id,
                            'master_consent_id' => (string) $latestConsent->id,
                            'status' => 'confirmed',
                        ];
                    }

                    $visitConfirmationId = (string) Str::uuid();
                    ConsentVisitConfirmation::query()->create([
                        'id' => $visitConfirmationId,
                        'booking_id' => (string) $case->booking_id,
                        'member_id' => $memberId,
                        'member_master_consent_id' => (string) $latestConsent->id,
                        'examination_site_id' => (string) $booking->examination_site_id_snapshot,
                        'operator_site_id' => (string) $site->operator_site_id,
                        'confirmed_by_operator_id' => $profileId,
                        'confirmed_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $this->upsertExaminationConsent(
                        bookingId: (string) $case->booking_id,
                        memberId: $memberId,
                        examinationSiteId: (string) $booking->examination_site_id_snapshot,
                        operatorSiteId: (string) $site->operator_site_id,
                        signerType: (string) $latestConsent->signer_type,
                        signedAt: $latestConsent->signed_at,
                        operatorProfileId: $profileId,
                        operationId: $operationId,
                        scanObjectKey: $latestConsent->private_scan_object_key,
                        scanChecksum: $latestConsent->private_scan_checksum,
                        scanBytes: $latestConsent->private_scan_bytes !== null ? (int) $latestConsent->private_scan_bytes : null,
                        scanFormat: $latestConsent->private_scan_format,
                        now: $now,
                    );

                    $this->audit->append(AuditEvent::fromContext(
                        $identity['context'],
                        'operator.consent.visit-confirmed',
                        'operator',
                        'success',
                        $now,
                        ConsentVisitConfirmation::class,
                        $visitConfirmationId,
                        metadata: [
                            'master_consent_id' => (string) $latestConsent->id,
                            'consent_version' => $latestConsent->consent_version,
                            'booking_id' => (string) $case->booking_id,
                            'operator_site_id' => $site->operator_site_id,
                        ],
                    ));

                    return [
                        'visit_confirmation_id' => $visitConfirmationId,
                        'master_consent_id' => (string) $latestConsent->id,
                        'status' => 'confirmed',
                    ];
                });
            }
        );

        return (array) $outcome->result;
    }

    public function withdrawConsent(
        string $caseId,
        string $masterConsentId,
        string $reason,
        string $operationId
    ): MemberMasterConsent {
        $reason = trim($reason);
        if ($reason === '') {
            throw new OperatorException('consent_invalid', 'A withdrawal reason is required.');
        }

        $operationId = trim($operationId);
        if (! Str::isUuid($operationId)) {
            throw new OperatorException('consent_invalid', 'A valid operation ID is required.');
        }

        [$identity, $site, $case] = $this->matchedCase($caseId);
        $profileId = (string) $identity['profile']->getKey();
        $scheduleId = (string) $case->member_schedule_id;

        // Verify shift assignment
        if (! $this->assignments->isAssigned($profileId, $scheduleId, $site->operator_site_id)) {
            throw new OperatorException('consent_denied', 'The consent withdrawal could not be processed.');
        }

        $booking = DB::table('bookings')->where('id', $case->booking_id)->first();
        if ($booking === null) {
            throw new OperatorException('consent_unavailable', 'The consent withdrawal could not be processed.');
        }

        $memberId = (string) $booking->member_id;
        $now = $this->clock->now();

        $payload = [
            'case_id' => $caseId,
            'master_consent_id' => $masterConsentId,
            'member_id' => $memberId,
            'operator_site_id' => $site->operator_site_id,
        ];

        $this->idempotency->run(
            'consent-withdraw:'.$operationId,
            'operator.consent.withdraw',
            $payload,
            function () use ($identity, $profileId, $masterConsentId, $memberId, $reason, $now): array {
                return DB::transaction(function () use ($identity, $profileId, $masterConsentId, $memberId, $reason, $now): array {
                    $record = MemberMasterConsent::query()->whereKey($masterConsentId)->lockForUpdate()->first();
                    if ($record === null) {
                        throw new OperatorException('consent_unavailable', 'The consent withdrawal could not be processed.');
                    }

                    // Object-level authorization: ensure master consent belongs to the case's member
                    if ((string) $record->member_id !== $memberId) {
                        throw new OperatorException('consent_unavailable', 'The consent withdrawal could not be processed.');
                    }

                    if ($record->isWithdrawn()) {
                        return [
                            'id' => (string) $record->id,
                            'status' => 'withdrawn',
                        ];
                    }

                    $record->update([
                        'status' => 'withdrawn',
                        'withdrawn_at' => $now,
                        'withdrawn_reason' => $reason,
                        'withdrawn_by_operator_id' => $profileId,
                    ]);

                    // Invalidate any non-completed examination_consents for this member
                    $consentIdsToWithdraw = DB::table('examination_consents')
                        ->join('bookings', 'bookings.id', '=', 'examination_consents.booking_id')
                        ->where('examination_consents.member_id', $record->member_id)
                        ->whereIn('bookings.status', ['arrived', 'confirmed', 'checked_in'])
                        ->pluck('examination_consents.id');

                    if ($consentIdsToWithdraw->isNotEmpty()) {
                        DB::table('examination_consents')
                            ->whereIn('id', $consentIdsToWithdraw)
                            ->update(['status' => 'withdrawn']);
                    }

                    $this->audit->append(AuditEvent::fromContext(
                        $identity['context'],
                        'operator.consent.withdrawn',
                        'operator',
                        'success',
                        $now,
                        MemberMasterConsent::class,
                        $masterConsentId,
                        reason: 'consent_withdrawn',
                        metadata: [
                            'consent_version' => $record->consent_version,
                            'operator_id' => $profileId,
                        ],
                    ));

                    return [
                        'id' => (string) $record->id,
                        'status' => 'withdrawn',
                    ];
                });
            }
        );

        return MemberMasterConsent::findOrFail($masterConsentId);
    }

    public function assertConsentNotWithdrawn(string $bookingId): void
    {
        $booking = DB::table('bookings')->where('id', $bookingId)->first();
        if ($booking === null) {
            return;
        }

        $latestConsent = $this->getLatestMasterConsent((string) $booking->member_id);
        if ($latestConsent !== null && $latestConsent->isWithdrawn()) {
            throw new OperatorException('consent_withdrawn', 'Informed consent has been withdrawn. Procedure progression is blocked.');
        }

        $consent = DB::table('examination_consents')
            ->where('booking_id', $bookingId)
            ->first();
        if ($consent !== null && $consent->status === 'withdrawn') {
            throw new OperatorException('consent_withdrawn', 'Informed consent has been withdrawn. Procedure progression is blocked.');
        }
    }

    private function upsertExaminationConsent(
        string $bookingId,
        string $memberId,
        string $examinationSiteId,
        string $operatorSiteId,
        string $signerType,
        \DateTimeInterface $signedAt,
        string $operatorProfileId,
        string $operationId,
        ?string $scanObjectKey,
        ?string $scanChecksum,
        ?int $scanBytes,
        ?string $scanFormat,
        DateTimeImmutable $now,
    ): void {
        $existing = DB::table('examination_consents')->where('booking_id', $bookingId)->lockForUpdate()->first();
        if ($existing !== null) {
            $update = [
                'status' => 'confirmed',
                'signature_confirmed' => true,
                'updated_at' => $now,
            ];
            if ($scanObjectKey !== null && $existing->private_scan_object_key === null) {
                $update['private_scan_object_key'] = $scanObjectKey;
                $update['private_scan_checksum'] = $scanChecksum;
                $update['private_scan_bytes'] = $scanBytes;
                $update['private_scan_format'] = $scanFormat;
            }
            DB::table('examination_consents')->where('id', $existing->id)->update($update);

            return;
        }

        DB::table('examination_consents')->insert([
            'id' => (string) Str::uuid(),
            'member_id' => $memberId,
            'booking_id' => $bookingId,
            'examination_site_id' => $examinationSiteId,
            'operator_site_id' => $operatorSiteId,
            'form_name' => 'Informed Consent',
            'form_version' => 'V1',
            'signer_type' => $signerType,
            'signer_member_id' => $memberId,
            'signature_confirmed' => true,
            'signed_at' => $signedAt,
            'confirmed_by_operator_id' => $operatorProfileId,
            'recorded_at' => $now,
            'idempotency_id' => 'visit-confirm:'.$operationId,
            'private_scan_object_key' => $scanObjectKey,
            'private_scan_checksum' => $scanChecksum,
            'private_scan_bytes' => $scanBytes,
            'private_scan_format' => $scanFormat,
            'status' => 'confirmed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: object, 2: object} */
    private function matchedCase(string $caseId): array
    {
        $caseId = trim($caseId);
        if (! Str::isUuid($caseId)) {
            throw new OperatorException('consent_unavailable', 'The consent case is unavailable.');
        }

        $identity = $this->authorization->portal();
        $site = $this->authorization->portalSite($identity);
        $case = DB::table('operator_identity_verifications')
            ->where('id', $caseId)
            ->where('operator_site_id', $site->getKey())
            ->first();

        if ($case === null || ($case->state !== 'matched' && $case->state !== 'nonclinical_validation')) {
            throw new OperatorException('consent_unavailable', 'Only a matched identity verification case can manage consent.');
        }

        return [$identity, $site, $case];
    }

    /** @return array{contents: string, checksum: string, bytes: int, format: string} */
    private function validatedUpload(?UploadedFile $scan): array
    {
        if ($scan === null) {
            throw new OperatorException('consent_invalid', 'A private signed-paper upload is required.');
        }
        if (! $scan->isValid()) {
            throw new OperatorException('consent_invalid', 'The private signed-paper upload is invalid.');
        }

        $path = $scan->getRealPath();
        $bytes = is_string($path) && is_file($path) ? filesize($path) : false;
        $contents = is_string($path) && is_file($path) ? file_get_contents($path) : false;
        if (! is_int($bytes) || $bytes < 1 || $bytes > (int) config('mhcs.upload.max_file_bytes') || ! is_string($contents) || strlen($contents) !== $bytes) {
            throw new OperatorException('consent_invalid', 'The private signed-paper upload exceeds the approved boundary.');
        }

        $format = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        if (! is_string($format) || ! in_array($format, self::UPLOAD_FORMATS, true)) {
            throw new OperatorException('consent_invalid', 'The private signed-paper upload format is not supported.');
        }
        if ($format === 'application/pdf' && ! str_starts_with($contents, '%PDF-')) {
            throw new OperatorException('consent_invalid', 'The private signed-paper upload content is invalid.');
        }
        if (in_array($format, ['image/jpeg', 'image/png'], true)) {
            $image = @getimagesizefromstring($contents);
            $expectedType = $format === 'image/jpeg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;
            if (! is_array($image) || ($image[2] ?? null) !== $expectedType) {
                throw new OperatorException('consent_invalid', 'The private signed-paper upload content is invalid.');
            }
        }

        return [
            'contents' => $contents,
            'checksum' => hash('sha256', $contents),
            'bytes' => $bytes,
            'format' => $format,
        ];
    }

    private function instant(string $value): DateTimeImmutable
    {
        $raw = trim($value);
        if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $raw) === 1) {
            $raw .= 'T00:00:00Z';
        }

        return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('UTC'));
    }
}
