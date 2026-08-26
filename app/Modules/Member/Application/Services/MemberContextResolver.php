<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Contracts\NonclinicalValidationIdentityContract;
use App\Modules\Member\Domain\Enums\IdentityStatus;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Validation\NonclinicalValidationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class MemberContextResolver implements NonclinicalValidationIdentityContract
{
    public function resolveForUserId(string $userId): ?Member
    {
        $members = Member::query()->where('user_id', $userId)->get();

        return $members->count() === 1 ? $members->first() : null;
    }

    public function requireForUserId(string $userId): Member
    {
        $member = $this->resolveForUserId($userId);

        if ($member === null) {
            throw new MemberIdentityException('Member access is unavailable.');
        }

        return $member;
    }

    public function isEligibleAdult(Member $member): bool
    {
        return CarbonImmutable::parse((string) $member->birth_date)
            ->lessThanOrEqualTo(CarbonImmutable::today()->subYears(17));
    }

    public function completionPercentage(Member $member): int
    {
        $required = [
            $member->current_address,
            $member->emergency_contact_name,
            $member->emergency_contact_relationship,
            $member->emergency_contact_phone,
        ];

        return (int) (count(array_filter($required, static fn (mixed $value): bool => is_string($value) && trim($value) !== '')) * 25);
    }

    public function isComplete(Member $member): bool
    {
        return $this->completionPercentage($member) === 100;
    }

    public function isIdentityEligibleForBooking(Member $member): bool
    {
        $statusValidation = $member->identity_status === IdentityStatus::NonclinicalValidation->value;
        $sourceValidation = $member->registration_source === RegistrationSource::NonclinicalValidation->value;

        if (! $statusValidation && ! $sourceValidation && $member->identity_status === IdentityStatus::Verified->value) {
            return true;
        }

        return $statusValidation && $sourceValidation && $this->isExactNonclinicalValidationIdentity($member);
    }

    public function isExactNonclinicalValidationIdentity(Member $member): bool
    {
        if (
            $member->identity_status !== IdentityStatus::NonclinicalValidation->value
            || $member->registration_source !== RegistrationSource::NonclinicalValidation->value
            || $member->identity_document_type !== null
            || $member->encrypted_nik !== null
            || $member->nik_lookup_digest !== null
        ) {
            return false;
        }

        return DB::table('member_external_identifiers')
            ->where('member_id', $member->id)
            ->where('namespace', NonclinicalValidationContext::MARKER_NAMESPACE)
            ->where('value', NonclinicalValidationContext::KEY)
            ->count() === 1
            && ! DB::table('member_verification_assets')->where('member_id', $member->id)->exists();
    }

    public function isExactNonclinicalValidationMember(string $memberId): bool
    {
        $member = Member::query()->find($memberId);

        return $member !== null && $this->isExactNonclinicalValidationIdentity($member);
    }

    public function assertNonclinicalValidationConsistency(string $memberId): void
    {
        $member = Member::query()->find($memberId);
        if ($member === null) {
            throw new MemberIdentityException('The Member identity was not found.');
        }

        $statusValidation = $member->identity_status === IdentityStatus::NonclinicalValidation->value;
        $sourceValidation = $member->registration_source === RegistrationSource::NonclinicalValidation->value;
        if ($statusValidation !== $sourceValidation) {
            throw new MemberIdentityException('Nonclinical validation identity signals are inconsistent.');
        }
        if ($statusValidation && ! $this->isExactNonclinicalValidationIdentity($member)) {
            throw new MemberIdentityException('The nonclinical validation Member state is inconsistent.');
        }
    }
}
