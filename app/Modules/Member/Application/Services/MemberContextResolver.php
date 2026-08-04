<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Models\Member;
use Carbon\CarbonImmutable;

final class MemberContextResolver
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
}
