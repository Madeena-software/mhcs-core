<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorPaperConsentContract;
use App\Modules\Operator\Domain\Models\ConsentVisitConfirmation;
use App\Modules\Operator\Domain\Models\MemberMasterConsent;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Identity\LocalId;
use App\Shared\Infrastructure\ObjectStorage\ObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OperatorReusableConsentService
{
    public const DEFAULT_SCOPE = 'radiography_screening';

    public function __construct(
        private OperatorAuthorization $authorization,
        private AuditStore $audit,
        private Clock $clock,
        private ?ObjectStore $objects = null,
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
    ): array {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $profileId = (string) $identity['profile']->getKey();
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

        return DB::transaction(function () use (
            $identity, $site, $case, $profileId, $booking, $memberId, $signerType, $signedInstant, $operationId, $screeningScope, $scan, $now
        ): array {
            // Supersede any existing active consent
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
            $masterConsent = MemberMasterConsent::query()->create([
                'id' => $masterConsentId,
                'member_id' => $memberId,
                'consent_version' => $newVersion,
                'form_name' => 'Informed Consent',
                'form_version' => 'V1',
                'screening_scope' => $screeningScope,
                'signer_type' => $signerType,
                'signer_member_id' => $memberId,
                'signed_at' => $signedInstant,
                'status' => 'active',
                'withdrawn_at' => null,
                'withdrawn_reason' => null,
                'withdrawn_by_operator_id' => null,
                'created_by_operator_id' => $profileId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Create per-visit confirmation
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

            // Create or update backward-compatible examination_consents entry
            $this->upsertExaminationConsent(
                bookingId: (string) $case->booking_id,
                memberId: $memberId,
                examinationSiteId: (string) $booking->examination_site_id_snapshot,
                operatorSiteId: (string) $site->operator_site_id,
                signerType: $signerType,
                signedAt: $signedInstant,
                operatorProfileId: $profileId,
                operationId: $operationId,
                now: $now,
            );

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

    /** @return array<string, mixed> */
    public function confirmVisit(string $caseId, string $operationId, ?string $screeningScope = null): array
    {
        [$identity, $site, $case] = $this->matchedCase($caseId);
        $profileId = (string) $identity['profile']->getKey();
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

        return DB::transaction(function () use ($identity, $site, $case, $profileId, $booking, $memberId, $latestConsent, $operationId, $now): array {
            $existing = ConsentVisitConfirmation::query()
                ->where('booking_id', (string) $case->booking_id)
                ->first();

            if ($existing !== null) {
                return [
                    'visit_confirmation_id' => (string) $existing->id,
                    'master_consent_id' => (string) $existing->member_master_consent_id,
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

    public function withdrawConsent(string $masterConsentId, string $reason, string $operationId): MemberMasterConsent
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new OperatorException('consent_invalid', 'A withdrawal reason is required.');
        }

        $identity = $this->authorization->portal();
        $profileId = (string) $identity['profile']->getKey();
        $now = $this->clock->now();

        return DB::transaction(function () use ($identity, $profileId, $masterConsentId, $reason, $now): MemberMasterConsent {
            $record = MemberMasterConsent::query()->whereKey($masterConsentId)->lockForUpdate()->first();
            if ($record === null) {
                throw new OperatorException('consent_unavailable', 'The master consent record is unavailable.');
            }

            if ($record->isWithdrawn()) {
                return $record;
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

            return $record->refresh();
        });
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
        DateTimeImmutable $now,
    ): void {
        $existing = DB::table('examination_consents')->where('booking_id', $bookingId)->lockForUpdate()->first();
        if ($existing !== null) {
            DB::table('examination_consents')->where('id', $existing->id)->update([
                'status' => 'confirmed',
                'signature_confirmed' => true,
                'updated_at' => $now,
            ]);

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
            'private_scan_object_key' => null,
            'private_scan_checksum' => null,
            'private_scan_bytes' => null,
            'private_scan_format' => null,
            'status' => 'confirmed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: object, 2: object} */
    private function matchedCase(string $caseId): array
    {
        $identity = $this->authorization->portal();
        $site = $this->authorization->portalSite($identity);
        $case = DB::table('operator_identity_verifications')
            ->where('id', trim($caseId))
            ->where('operator_site_id', $site->getKey())
            ->first();

        if ($case === null || ($case->state !== 'matched' && $case->state !== 'nonclinical_validation')) {
            throw new OperatorException('consent_unavailable', 'Only a matched identity verification case can confirm consent.');
        }

        return [$identity, $site, $case];
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
