<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\TrustedOperatorIdentityVerificationContextResolver;
use App\Modules\Member\Application\Data\VerificationAssetInput;
use App\Modules\Member\Domain\Enums\IdentityStatus;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Modules\Member\Domain\Enums\VerificationReviewStatus;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContext;
use App\Shared\Storage\AccessGrant;
use App\Shared\Storage\OpaqueObjectKey;
use App\Shared\Storage\PrivateObject;
use App\Shared\Storage\PrivateObjectStore;
use App\Shared\Time\Clock;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class MemberVerificationAssetService
{
    public function __construct(
        private MemberAuthorization $authorization,
        private TrustedOperatorIdentityVerificationContextResolver $trustedCase,
        private PrivateObjectStore $objects,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function recordForRegistration(
        Member $member,
        VerificationAssetInput $input,
        AuthenticatedContext $callerContext,
    ): string {
        $context = $this->registrationContext($member);
        $this->assertCallerContextMatchesTrustedContext($callerContext, $context);

        $approved = $this->authorization->hasAdministratorPermission(
            $context,
            MemberAuthorization::IDENTITY_VERIFICATION_PERMISSION,
        );

        return DB::transaction(fn (): string => $this->record($member, $input, $context, $approved));
    }

    public function review(string $assetId, bool $approve, string $purpose = 'member.identity.verify'): void
    {
        if ($purpose !== 'member.identity.verify') {
            throw new MemberIdentityException('The verification review purpose is not supported.');
        }

        $context = $this->authorization->identityVerification();
        DB::transaction(function () use ($assetId, $approve, $context): void {
            $asset = DB::table('member_verification_assets')->where('id', $assetId)->first();

            if ($asset === null) {
                throw new MemberIdentityException('The verification asset was not found.');
            }

            $member = DB::table('members')->where('id', $asset->member_id)->lockForUpdate()->first();
            if ($member === null) {
                throw new MemberIdentityException('The Member identity was not found.');
            }

            $asset = DB::table('member_verification_assets')->where('id', $assetId)->lockForUpdate()->first();
            if ($asset === null) {
                throw new MemberIdentityException('The verification asset was not found.');
            }

            $now = $this->clock->now();
            if ($approve) {
                $this->assertTypeForBirthDate((string) $member->birth_date, VerificationAssetType::from($asset->type));
                $this->demoteCurrent($asset->member_id, VerificationAssetType::from($asset->type), $now);
            }

            DB::table('member_verification_assets')->where('id', $assetId)->update([
                'review_status' => $approve ? VerificationReviewStatus::Approved->value : VerificationReviewStatus::Rejected->value,
                'is_current' => $approve,
                'reviewed_by_user_id' => (string) $context->actorId,
                'reviewed_at' => $now,
                'updated_at' => $now,
            ]);

            $this->syncIdentityStatus($asset->member_id, (string) $member->birth_date);
            $this->audit->append(AuditEvent::fromContext(
                $context,
                action: 'member.verification-asset.reviewed',
                source: 'member',
                outcome: $approve ? 'approved' : 'rejected',
                occurredAt: $now,
                targetType: Member::class,
                targetId: $asset->member_id,
                metadata: ['asset_type' => $asset->type],
            ));
        });
    }

    public function grant(string $assetId, string $audience, string $purpose, DateTimeImmutable $expiresAt): AccessGrant
    {
        $asset = DB::table('member_verification_assets')->where('id', $assetId)->first();

        if (
            $asset === null
            || $asset->review_status !== VerificationReviewStatus::Approved->value
            || ! (bool) $asset->is_current
        ) {
            throw new MemberIdentityException('The requested verification asset is unavailable.');
        }

        $context = $this->authorization->assetAccess($asset->member_id, $purpose);
        $this->assertGrantBounds($audience, $expiresAt);

        return $this->objects->grant(
            new PrivateObject(
                key: OpaqueObjectKey::fromString($asset->private_object_key),
                checksum: $asset->checksum,
                bytes: (int) $asset->bytes,
                encryption: 'AES-256-GCM',
                createdAt: new DateTimeImmutable((string) $asset->created_at),
            ),
            $context,
            $audience,
            $purpose,
            $expiresAt,
        );
    }

    public function retrieve(AccessGrant $grant, string $audience, string $purpose): string
    {
        return $this->objects->get($grant, $this->authorization->context($purpose), $audience, $purpose);
    }

    public function grantForOperator(
        string $assetId,
        AuthenticatedContext $caller,
        string $operatorSiteId,
        string $scheduleId,
        string $bookingId,
        string $caseId,
        string $audience,
        DateTimeImmutable $expiresAt,
    ): AccessGrant {
        $assertion = $this->trustedCase->resolve($caller, $operatorSiteId, $scheduleId, $bookingId, $caseId);
        $asset = DB::table('member_verification_assets')->where('id', $assetId)->first();

        if (
            $assertion === null
            || $asset === null
            || (string) $asset->member_id !== $assertion['member_id']
            || $asset->review_status !== VerificationReviewStatus::Approved->value
            || (! (bool) $asset->is_current
                && ((string) $asset->type !== VerificationAssetType::ProfilePhoto->value || ! $assertion['prior_photos_revealed']))
        ) {
            throw new MemberIdentityException('The requested verification asset is unavailable.');
        }

        $context = $this->authorization->operatorIdentityAsset($caller);
        $this->assertGrantBounds($audience, $expiresAt);

        return $this->objects->grant(
            new PrivateObject(
                key: OpaqueObjectKey::fromString($asset->private_object_key),
                checksum: $asset->checksum,
                bytes: (int) $asset->bytes,
                encryption: 'AES-256-GCM',
                createdAt: new DateTimeImmutable((string) $asset->created_at),
            ),
            $context,
            $audience,
            'operator.identity.asset',
            $expiresAt,
        );
    }

    private function record(
        Member $member,
        VerificationAssetInput $input,
        AuthenticatedContext $context,
        bool $approved,
    ): string {
        $lockedMember = DB::table('members')->where('id', $member->id)->lockForUpdate()->first();
        if ($lockedMember === null) {
            throw new MemberIdentityException('The Member identity was not found.');
        }

        $this->assertTypeForBirthDate((string) $lockedMember->birth_date, $input->type);
        $this->assertPrivateObject($input);

        if ($input->replacesId !== null) {
            $replacement = DB::table('member_verification_assets')
                ->where('id', $input->replacesId)
                ->where('member_id', $lockedMember->id)
                ->first();

            if ($replacement === null || ! $this->sameAssetSlot($replacement->type, $input->type)) {
                throw new MemberIdentityException('A replacement asset must belong to the same Member and asset slot.');
            }
        }

        $now = $this->clock->now();
        $assetId = (string) Str::uuid();
        $status = $approved ? VerificationReviewStatus::Approved : VerificationReviewStatus::Pending;

        if ($approved) {
            $this->demoteCurrent($lockedMember->id, $input->type, $now);
        }

        DB::table('member_verification_assets')->insert([
            'id' => $assetId,
            'member_id' => $lockedMember->id,
            'type' => $input->type->value,
            'private_object_key' => (string) $input->object->key,
            'checksum' => $input->object->checksum,
            'bytes' => $input->object->bytes,
            'format' => $input->format,
            'review_status' => $status->value,
            'is_current' => $approved,
            'uploaded_by_user_id' => (string) $context->actorId,
            'reviewed_by_user_id' => $approved ? (string) $context->actorId : null,
            'reviewed_at' => $approved ? $now : null,
            'replaces_id' => $input->replacesId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->syncIdentityStatus($lockedMember->id, (string) $lockedMember->birth_date);
        $this->audit->append(AuditEvent::fromContext(
            $context,
            action: 'member.verification-asset.recorded',
            source: 'member',
            outcome: $status->value,
            occurredAt: $now,
            targetType: Member::class,
            targetId: (string) $lockedMember->id,
            metadata: ['asset_type' => $input->type->value, 'current' => $approved],
        ));

        return $assetId;
    }

    private function assertPrivateObject(VerificationAssetInput $input): void
    {
        if (
            $input->object->encryption !== 'AES-256-GCM'
            || $input->object->bytes < 1
            || preg_match('/\A[0-9a-f]{64}\z/i', $input->object->checksum) !== 1
            || ! str_starts_with((string) $input->object->key, 'objects/')
        ) {
            throw new MemberIdentityException('Verification assets must come from the private encrypted-object boundary.');
        }
    }

    private function assertTypeForBirthDate(string $birthDate, VerificationAssetType $type): void
    {
        if ($type === VerificationAssetType::ProfilePhoto) {
            return;
        }

        $age = (new DateTimeImmutable($birthDate))->diff($this->clock->now())->y;
        $expected = $age >= 17 ? VerificationAssetType::Ktp : VerificationAssetType::Kia;
        if ($type !== $expected) {
            throw new MemberIdentityException('The identity document does not match the standard age path.');
        }
    }

    private function sameAssetSlot(string $existingType, VerificationAssetType $newType): bool
    {
        if ($newType === VerificationAssetType::ProfilePhoto) {
            return $existingType === VerificationAssetType::ProfilePhoto->value;
        }

        return in_array($existingType, [VerificationAssetType::Ktp->value, VerificationAssetType::Kia->value], true);
    }

    private function demoteCurrent(string $memberId, VerificationAssetType $type, DateTimeImmutable $now): void
    {
        $query = DB::table('member_verification_assets')
            ->where('member_id', $memberId)
            ->where('is_current', true);

        if ($type === VerificationAssetType::ProfilePhoto) {
            $query->where('type', $type->value);
        } else {
            $query->whereIn('type', [VerificationAssetType::Ktp->value, VerificationAssetType::Kia->value]);
        }

        $query->update(['is_current' => false, 'updated_at' => $now]);
    }

    private function assertGrantBounds(string $audience, DateTimeImmutable $expiresAt): void
    {
        $policy = config('mhcs.security.asset_grants');
        $maximum = is_array($policy) ? $policy['max_ttl_seconds'] ?? null : null;
        $audiences = is_array($policy) ? $policy['audiences'] ?? null : null;

        if (! is_int($maximum) && ! (is_string($maximum) && ctype_digit($maximum))) {
            throw new MemberIdentityException('Verification asset grant policy is not configured.');
        }

        if (! is_array($audiences) || $audiences === [] || ! in_array($audience, $audiences, true)) {
            throw new MemberIdentityException('The verification asset grant audience is not trusted.');
        }

        $ttl = $expiresAt->getTimestamp() - $this->clock->now()->getTimestamp();
        if ($ttl <= 0 || $ttl > (int) $maximum) {
            throw new MemberIdentityException('The verification asset grant lifetime exceeds the approved boundary.');
        }
    }

    private function syncIdentityStatus(string $memberId, ?string $birthDate = null): void
    {
        $member = DB::table('members')->where('id', $memberId)->first();
        if ($member === null) {
            throw new MemberIdentityException('The Member identity was not found.');
        }

        $birthDate ??= (string) $member->birth_date;
        $expected = (new DateTimeImmutable($birthDate))->diff($this->clock->now())->y >= 17 ? 'ktp' : 'kia';
        $verified = DB::table('member_verification_assets')
            ->where('member_id', $memberId)
            ->where('type', $expected)
            ->where('review_status', VerificationReviewStatus::Approved->value)
            ->where('is_current', true)
            ->exists()
            && DB::table('member_verification_assets')
                ->where('member_id', $memberId)
                ->where('type', VerificationAssetType::ProfilePhoto->value)
                ->where('review_status', VerificationReviewStatus::Approved->value)
                ->where('is_current', true)
                ->exists();

        $updates = [
            'identity_status' => $verified ? IdentityStatus::Verified->value : IdentityStatus::PendingVerification->value,
            'updated_at' => $this->clock->now(),
        ];
        $documentType = DB::table('member_verification_assets')
            ->where('member_id', $memberId)
            ->whereIn('type', [VerificationAssetType::Ktp->value, VerificationAssetType::Kia->value])
            ->where('review_status', VerificationReviewStatus::Approved->value)
            ->where('is_current', true)
            ->lockForUpdate()
            ->value('type');
        if (is_string($documentType)) {
            $updates['identity_document_type'] = $documentType;
        }

        DB::table('members')->where('id', $memberId)->update($updates);
    }

    private function registrationContext(Member $member): AuthenticatedContext
    {
        $source = RegistrationSource::tryFrom((string) $member->registration_source);
        if ($source === null) {
            throw new MemberIdentityException('The Member registration source is invalid.');
        }

        $adult = new DateTimeImmutable((string) $member->birth_date)
            <= $this->clock->now()->modify('-17 years')->setTime(0, 0);
        $context = $this->authorization->registration($source, $adult);

        if ($source === RegistrationSource::Online && (string) $context->actorId !== (string) $member->user_id) {
            throw new MemberIdentityException('Online registration assets must belong to the authenticated Member account.');
        }

        return $context;
    }

    private function assertCallerContextMatchesTrustedContext(
        AuthenticatedContext $caller,
        AuthenticatedContext $trusted,
    ): void {
        if (
            $caller->actorId?->value !== $trusted->actorId?->value
            || $caller->operationId?->value !== $trusted->operationId?->value
            || $caller->sessionId?->value !== $trusted->sessionId?->value
            || $caller->siteId?->value !== $trusted->siteId?->value
            || $caller->caseId?->value !== $trusted->caseId?->value
            || $caller->purpose !== $trusted->purpose
            || $caller->roles !== $trusted->roles
            || $caller->permissions !== $trusted->permissions
        ) {
            throw new MemberIdentityException('Registration asset recording requires the trusted registration context.');
        }
    }
}
