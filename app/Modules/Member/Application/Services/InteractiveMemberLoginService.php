<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Application\Data\InteractiveMemberLoginResult;
use App\Shared\Security\CredentialVerifier;

final readonly class InteractiveMemberLoginService
{
    public function __construct(
        private CredentialVerifier $credentials,
        private MemberContextResolver $members,
    ) {}

    public function authenticate(string $identifier, string $password): InteractiveMemberLoginResult
    {
        $result = $this->credentials->verifyForInteractiveLogin($identifier, $password);

        if (! $result->authenticated || $result->user === null) {
            return InteractiveMemberLoginResult::failure($result->rateLimited);
        }

        $member = $this->members->resolveForUserId((string) $result->user->getAuthIdentifier());
        if ($member === null || ! $this->members->isEligibleAdult($member)) {
            return InteractiveMemberLoginResult::failure();
        }

        return $result->user->must_change_password
            ? InteractiveMemberLoginResult::passwordChangeRequired($result->user)
            : InteractiveMemberLoginResult::normal($result->user);
    }
}
