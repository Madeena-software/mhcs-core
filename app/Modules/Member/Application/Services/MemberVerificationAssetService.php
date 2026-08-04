<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Data\VerificationAssetInput;
use App\Modules\Member\Domain\Enums\IdentityStatus;
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
        private PrivateObjectStore $objects,
        private AuditStore $audit,
        private Clock $clock,
    ) {}

    public function recordInTransaction(
        Member $member,
        VerificationAssetInput $input,
        AuthenticatedContext $context,
        bool $approved,
    ): string {
        $this->assertType($member, $input->type);

        if (
            $input->object->encryption !== 'AES-256-GCM'
            || $input->object->bytes < 1
            || preg_match('/\A[0-9a-f]{64}\z/i', $input->object->checksum) !== 1
            || ! str_starts_with((string) $input->object->key, 'objects/')
        ) {
            throw new MemberIdentityException('Verification assets must come from the private encrypted-object boundary.');
        }

        if ($input->replacesId !== null) {
            $replacement = DB::table('member_verification_assets')
                ->where('id', $input->replacesId)
                ->where('member_id', $member->id)
                ->where('type', $input->type->value)
                ->first();

            if ($replacement === null) {
                throw new MemberIdentityException('A replacement asset must belong to the same Member and type.');
            }
        }

        $now = $this->clock->now();
        $assetId = (string) Str::uuid();
        $status = $approved ? VerificationReviewStatus::Approved : VerificationReviewStatus::Pending;
        $current = $input->type->isIdentityDocument() || $approved;

        if ($current) {
            DB::table('member_verification_assets')
                ->where('member_id', $member->id)
                ->where('type', $input->type->value)
                ->where('is_current', true)
                ->update(['is_current' => false, 'updated_at' => $now]);
        }

        DB::table('member_verification_assets')->insert([
            'id' => $assetId,
            'member_id' => $member->id,
            'type' => $input->type->value,
            'private_object_key' => (string) $input->object->key,
            'checksum' => $input->object->checksum,
            'bytes' => $input->object->bytes,
            'format' => $input->format,
            'review_status' => $status->value,
            'is_current' => $current,
            'uploaded_by_user_id' => (string) $context->actorId,
            'reviewed_by_user_id' => $approved ? (string) $context->actorId : null,
            'reviewed_at' => $approved ? $now : null,
            'replaces_id' => $input->replacesId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->syncIdentityStatus($member->id, $member->birth_date->format('Y-m-d'));
        $this->audit->append(AuditEvent::fromContext(
            $context,
            action: 'member.verification-asset.recorded',
            source: 'member',
            outcome: $status->value,
            occurredAt: $now,
            targetType: Member::class,
            targetId: (string) $member->id,
            metadata: ['asset_type' => $input->type->value, 'current' => $current],
        ));

        return $assetId;
    }

    public function review(string $assetId, bool $approve, string $purpose = 'member.identity.verify'): void
    {
        $context = $this->authorization->administrator($purpose);
        DB::transaction(function () use ($assetId, $approve, $context): void {
            $asset = DB::table('member_verification_assets')->where('id', $assetId)->lockForUpdate()->first();

            if ($asset === null) {
                throw new MemberIdentityException('The verification asset was not found.');
            }

            $now = $this->clock->now();
            if ($approve) {
                DB::table('member_verification_assets')
                    ->where('member_id', $asset->member_id)
                    ->where('type', $asset->type)
                    ->where('is_current', true)
                    ->update(['is_current' => false, 'updated_at' => $now]);
            }

            DB::table('member_verification_assets')->where('id', $assetId)->update([
                'review_status' => $approve ? VerificationReviewStatus::Approved->value : VerificationReviewStatus::Rejected->value,
                'is_current' => $approve,
                'reviewed_by_user_id' => (string) $context->actorId,
                'reviewed_at' => $now,
                'updated_at' => $now,
            ]);

            $this->syncIdentityStatus($asset->member_id);
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
        $context = $this->authorization->context($purpose);
        $asset = DB::table('member_verification_assets')->where('id', $assetId)->first();

        if ($asset === null || $asset->review_status !== VerificationReviewStatus::Approved->value) {
            throw new MemberIdentityException('The requested verification asset is unavailable.');
        }

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

    private function assertType(Member $member, VerificationAssetType $type): void
    {
        $age = $member->birth_date->diff($this->clock->now())->y;
        if ($type === VerificationAssetType::ProfilePhoto) {
            return;
        }

        $expected = $age >= 17 ? VerificationAssetType::Ktp : VerificationAssetType::Kia;
        if ($type !== $expected) {
            throw new MemberIdentityException('The identity document does not match the standard age path.');
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

        DB::table('members')->where('id', $memberId)->update([
            'identity_status' => $verified ? IdentityStatus::Verified->value : IdentityStatus::PendingVerification->value,
            'updated_at' => $this->clock->now(),
        ]);
    }
}
