<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\OperatorIdentityVerificationContract;
use App\Modules\Member\Application\Contracts\TrustedOperatorIdentityVerificationContextResolver;
use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Modules\Member\Domain\Enums\VerificationReviewStatus;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Security\ProtectedIdentifierService;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class Mvp04OperatorIdentityVerificationService implements OperatorIdentityVerificationContract
{
    private const LOOKUP_PURPOSE = 'operator.identity.lookup';

    private const VIEW_PURPOSE = 'operator.identity.view';

    private const PREVIOUS_PURPOSE = 'operator.identity.previous';

    private const ASSET_PURPOSE = 'operator.identity.asset';

    private const AUDIENCE = 'operator-identity';

    public function __construct(
        private ProtectedIdentifierService $identifiers,
        private MemberVerificationAssetService $assets,
        private TrustedOperatorIdentityVerificationContextResolver $trustedCase,
        private AuditStore $audit,
        private Clock $clock,
        private MemberContextResolver $members,
    ) {}

    public function lookupByNik(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $nik,
        string $at,
    ): array {
        $this->assertCase($context, $operatorSiteId, $scheduleId, $bookingId, $caseId, self::LOOKUP_PURPOSE);
        $occurrence = $this->instant($at);
        $result = 'unavailable';

        try {
            $digest = $this->identifiers->lookupDigest($nik);
            $rows = $this->eligibleBookingQuery($operatorSiteId)
                ->join('members', 'members.id', '=', 'bookings.member_id')
                ->where('members.nik_lookup_digest', $digest)
                ->where('bookings.shift_schedule_id', $scheduleId)
                ->where('bookings.status', 'arrived')
                ->select([
                    'bookings.id as booking_id',
                    'bookings.shift_schedule_id as schedule_id',
                    'bookings.status as booking_status',
                    'members.id as member_id',
                    'members.name as member_name',
                    'members.medical_record_number as medical_record_number',
                    'members.encrypted_nik as encrypted_nik',
                ])
                ->distinct()
                ->get();

            if ($rows->count() !== 1 || (string) $rows->first()->booking_id !== trim($bookingId)) {
                throw new MemberIdentityException('Identity verification lookup is unavailable.');
            }

            $row = $rows->first();
            $result = 'matched';
            $this->audit->append(AuditEvent::fromContext(
                $context,
                'member.operator-identity.lookup',
                'member',
                'success',
                $occurrence,
                'booking',
                (string) $row->booking_id,
                metadata: [
                    'operator_site_id' => $operatorSiteId,
                    'schedule_id' => $scheduleId,
                    'case_id' => $context->caseId === null ? null : (string) $context->caseId,
                    'purpose' => self::LOOKUP_PURPOSE,
                    'result' => $result,
                ],
            ));

            return [
                'booking_id' => (string) $row->booking_id,
                'schedule_id' => (string) $row->schedule_id,
                'member_id' => (string) $row->member_id,
                'member_name' => (string) $row->member_name,
                'medical_record_number' => (string) $row->medical_record_number,
                'nik' => $this->identifiers->display((string) $row->encrypted_nik),
                'masked_nik' => $this->maskedIdentifier($row->encrypted_nik),
                'booking_status' => (string) $row->booking_status,
            ];
        } catch (MemberIdentityException $exception) {
            $this->failure($context, $operatorSiteId, $scheduleId, $bookingId, $occurrence);
            throw $exception;
        } catch (Throwable $exception) {
            $this->failure($context, $operatorSiteId, $scheduleId, $bookingId, $occurrence);
            throw new MemberIdentityException('Identity verification lookup is unavailable.', previous: $exception);
        }
    }

    public function currentView(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
    ): array {
        $assertion = $this->assertCase($context, $operatorSiteId, $scheduleId, $bookingId, $caseId, self::VIEW_PURPOSE);
        $booking = $this->booking($operatorSiteId, $scheduleId, $bookingId);
        $member = Member::query()->find($assertion['member_id']);
        if ($member === null) {
            throw new MemberIdentityException('Identity verification view is unavailable.');
        }

        if ($this->members->isExactNonclinicalValidationIdentity($member)) {
            return ['evidence_status' => 'nonclinical_validation', 'view' => [
                'booking_id' => $booking->booking_id, 'schedule_id' => $booking->schedule_id,
                'member_id' => (string) $member->id, 'member_name' => (string) $member->name,
                'medical_record_number' => (string) $member->medical_record_number,
                'nik' => null, 'masked_nik' => null, 'booking_status' => (string) $booking->booking_status,
                'site' => (string) $booking->site_name, 'service_code' => (string) $booking->service_code,
                'service_name' => (string) $booking->service_name,
                'identity_document' => null, 'latest_profile_photo' => null,
            ]];
        }

        $expected = $this->expectedDocument((string) $member->birth_date);
        $assets = DB::table('member_verification_assets')
            ->where('member_id', $member->id)
            ->where('review_status', VerificationReviewStatus::Approved->value)
            ->where('is_current', true)
            ->whereIn('type', [$expected->value, VerificationAssetType::ProfilePhoto->value])
            ->get()
            ->keyBy('type');

        $result = [
            'booking_id' => $booking->booking_id,
            'schedule_id' => $booking->schedule_id,
            'member_id' => (string) $member->id,
            'member_name' => (string) $member->name,
            'medical_record_number' => (string) $member->medical_record_number,
            'nik' => $this->identifiers->display((string) $member->encrypted_nik),
            'masked_nik' => $this->maskedIdentifier($member->encrypted_nik),
            'booking_status' => (string) $booking->booking_status,
            'site' => (string) $booking->site_name,
            'service_code' => (string) $booking->service_code,
            'service_name' => (string) $booking->service_name,
            'identity_document' => $this->assetMetadata($assets->get($expected->value)),
            'latest_profile_photo' => $this->assetMetadata($assets->get(VerificationAssetType::ProfilePhoto->value)),
        ];
        if ($result['identity_document'] === null || $result['latest_profile_photo'] === null) {
            return ['evidence_status' => 'unavailable', 'view' => null];
        }

        $this->audit->append(AuditEvent::fromContext(
            $context,
            'member.operator-identity.view',
            'member',
            'success',
            $this->clock->now(),
            'booking',
            $booking->booking_id,
            metadata: [
                'operator_site_id' => $operatorSiteId,
                'schedule_id' => $scheduleId,
                'case_id' => $caseId,
                'purpose' => self::VIEW_PURPOSE,
            ],
        ));

        return ['evidence_status' => 'available', 'view' => $result];
    }

    public function revealPreviousPhotos(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $reason,
    ): array {
        $assertion = $this->assertCase($context, $operatorSiteId, $scheduleId, $bookingId, $caseId, self::PREVIOUS_PURPOSE);
        $reason = trim($reason);
        if ($reason === '' || strlen($reason) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $reason) === 1) {
            throw new MemberIdentityException('A bounded reason is required to reveal previous photos.');
        }
        $replay = $assertion['prior_photos_revealed'];
        if ($replay && $reason !== 'prior_photo_reveal_replay') {
            throw new MemberIdentityException('Previous profile photos require the existing audited reveal.');
        }
        if (! $replay && $reason === 'prior_photo_reveal_replay') {
            throw new MemberIdentityException('Previous profile photos require an explicit reveal command.');
        }
        $booking = $this->booking($operatorSiteId, $scheduleId, $bookingId);
        $member = DB::table('members')->where('id', $booking->member_id)->first();
        if ($member === null) {
            throw new MemberIdentityException('Identity verification view is unavailable.');
        }

        $photoRows = DB::table('member_verification_assets')
            ->where('member_id', $member->id)
            ->where('type', VerificationAssetType::ProfilePhoto->value)
            ->where('review_status', VerificationReviewStatus::Approved->value)
            ->where('is_current', false)
            ->orderByDesc('created_at')
            ->get();
        $photos = [];
        foreach ($photoRows as $asset) {
            $photos[] = $this->assetMetadata($asset);
        }

        if ($replay) {
            return $photos;
        }

        $this->audit->append(AuditEvent::fromContext(
            $context,
            'member.operator-identity.previous-photos.revealed',
            'member',
            'success',
            $this->clock->now(),
            'booking',
            $booking->booking_id,
            reason: 'latest_photo_insufficient',
            metadata: [
                'operator_site_id' => $operatorSiteId,
                'schedule_id' => $scheduleId,
                'case_id' => $caseId,
                'purpose' => self::PREVIOUS_PURPOSE,
                'result_count' => count($photos),
            ],
        ));

        return $photos;
    }

    public function retrieveAsset(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $assetId,
    ): array {
        $assertion = $this->assertCase($context, $operatorSiteId, $scheduleId, $bookingId, $caseId, self::ASSET_PURPOSE);
        $booking = $this->booking($operatorSiteId, $scheduleId, $bookingId);
        $asset = DB::table('member_verification_assets')
            ->where('id', $assetId)
            ->where('member_id', $assertion['member_id'])
            ->where('review_status', VerificationReviewStatus::Approved->value)
            ->first();
        if ($asset === null) {
            throw new MemberIdentityException('The requested verification asset is unavailable.');
        }

        $member = DB::table('members')->where('id', $assertion['member_id'])->first();
        if ($member === null) {
            throw new MemberIdentityException('The requested verification asset is unavailable.');
        }
        $expected = $this->expectedDocument((string) $member->birth_date);
        $isCurrentAllowed = (bool) $asset->is_current
            && in_array((string) $asset->type, [$expected->value, VerificationAssetType::ProfilePhoto->value], true);
        $isPreviousAllowed = ! (bool) $asset->is_current
            && (string) $asset->type === VerificationAssetType::ProfilePhoto->value
            && $assertion['prior_photos_revealed'];
        if (! $isCurrentAllowed && ! $isPreviousAllowed) {
            throw new MemberIdentityException('The requested verification asset is unavailable.');
        }

        $grant = $this->assets->grantForOperator(
            (string) $asset->id,
            $context,
            $operatorSiteId,
            $scheduleId,
            $bookingId,
            $caseId,
            self::AUDIENCE,
            $this->clock->now()->modify('+'.(int) config('mhcs.security.asset_grants.max_ttl_seconds', 300).' seconds'),
        );
        $contents = $this->assets->retrieve($grant, self::AUDIENCE, self::ASSET_PURPOSE);
        $format = is_string($asset->format) && preg_match('/\A[a-z0-9.+-]+\/[a-z0-9.+-]+\z/i', $asset->format) === 1
            ? $asset->format
            : 'application/octet-stream';

        $this->audit->append(AuditEvent::fromContext(
            $context,
            'member.operator-identity.asset.retrieved',
            'member',
            'success',
            $this->clock->now(),
            'booking',
            $booking->booking_id,
            metadata: [
                'operator_site_id' => $operatorSiteId,
                'schedule_id' => $scheduleId,
                'case_id' => $caseId,
                'purpose' => self::ASSET_PURPOSE,
                'asset_slot' => (string) $asset->type,
            ],
        ));

        return ['contents' => $contents, 'format' => $format];
    }

    /** @return array<string, string|bool> */
    private function assertCase(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $purpose,
    ): array {
        if ($context->purpose !== $purpose) {
            throw new MemberIdentityException('A trusted Operator identity context is required.');
        }
        $assertion = $this->trustedCase->resolve($context, $operatorSiteId, $scheduleId, $bookingId, $caseId);
        if ($assertion === null) {
            throw new MemberIdentityException('The trusted verification case is unavailable.');
        }

        return $assertion;
    }

    private function booking(string $operatorSiteId, string $scheduleId, string $bookingId): object
    {
        $booking = $this->eligibleBookingQuery($operatorSiteId)
            ->join('service_offerings', 'service_offerings.id', '=', 'bookings.service_offering_id')
            ->where('bookings.id', $bookingId)
            ->where('bookings.shift_schedule_id', $scheduleId)
            ->where('bookings.status', 'arrived')
            ->select([
                'bookings.id as booking_id',
                'bookings.shift_schedule_id as schedule_id',
                'bookings.member_id',
                'bookings.status as booking_status',
                'examination_site_refs.display_name as site_name',
                'service_offerings.code as service_code',
                'service_offerings.name as service_name',
            ])
            ->first();

        if ($booking === null) {
            throw new MemberIdentityException('Identity verification view is unavailable.');
        }

        return $booking;
    }

    private function eligibleBookingQuery(string $operatorSiteId): Builder
    {
        return DB::table('bookings')
            ->join('shift_schedules', 'shift_schedules.id', '=', 'bookings.shift_schedule_id')
            ->join('examination_site_refs', 'examination_site_refs.id', '=', 'shift_schedules.examination_site_id')
            ->where('examination_site_refs.operator_site_id', $operatorSiteId)
            ->where('examination_site_refs.active', true)
            ->whereColumn('bookings.examination_site_id_snapshot', 'examination_site_refs.id')
            ->whereExists(function (Builder $query) use ($operatorSiteId): void {
                $query->selectRaw('1')
                    ->from('operator_arrivals')
                    ->join('operator_sites', 'operator_sites.id', '=', 'operator_arrivals.operator_site_id')
                    ->whereColumn('operator_arrivals.booking_id', 'bookings.id')
                    ->where('operator_arrivals.status', 'recorded')
                    ->where('operator_sites.operator_site_id', $operatorSiteId);
            });
    }

    private function expectedDocument(string $birthDate): VerificationAssetType
    {
        return (new DateTimeImmutable($birthDate, new DateTimeZone('UTC')))
            ->diff($this->clock->now())->y >= 17
            ? VerificationAssetType::Ktp
            : VerificationAssetType::Kia;
    }

    /** @return array<string, mixed>|null */
    private function assetMetadata(?object $asset): ?array
    {
        if ($asset === null) {
            return null;
        }

        return [
            'asset_id' => (string) $asset->id,
            'type' => (string) $asset->type,
            'format' => (string) ($asset->format ?: 'application/octet-stream'),
            'bytes' => (int) $asset->bytes,
            'current' => (bool) $asset->is_current,
        ];
    }

    private function maskedIdentifier(?string $encrypted): ?string
    {
        if (! is_string($encrypted) || trim($encrypted) === '') {
            return null;
        }
        try {
            $value = $this->identifiers->display($encrypted);
        } catch (Throwable) {
            return null;
        }
        if (strlen($value) < 5) {
            return null;
        }

        return str_repeat('*', strlen($value) - 4).substr($value, -4);
    }

    private function instant(string $value): DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})\z/', $value) !== 1) {
            throw new MemberIdentityException('Identity verification time requires an explicit offset.');
        }
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Throwable $exception) {
            throw new MemberIdentityException('Identity verification time is invalid.', previous: $exception);
        }
    }

    private function failure(AuthenticatedContext $context, string $operatorSiteId, string $scheduleId, string $bookingId, DateTimeImmutable $occurredAt): void
    {
        $this->audit->append(AuditEvent::fromContext(
            $context,
            'member.operator-identity.lookup',
            'member',
            'failure',
            $occurredAt,
            'booking',
            trim($bookingId) === '' ? null : trim($bookingId),
            reason: 'identity_lookup_unavailable',
            metadata: ['operator_site_id' => $operatorSiteId, 'schedule_id' => $scheduleId, 'purpose' => self::LOOKUP_PURPOSE, 'result' => 'unavailable'],
        ));
    }
}
