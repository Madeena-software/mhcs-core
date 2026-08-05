<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Contracts\InteractiveOperatorAccessResolver;
use App\Modules\Member\Application\Data\InteractiveMemberLoginResult;
use App\Shared\Security\CredentialVerifier;

final readonly class InteractiveMemberLoginService
{
    public function __construct(
        private CredentialVerifier $credentials,
        private MemberContextResolver $members,
        private InteractiveOperatorAccessResolver $operators,
    ) {}

    public function authenticate(string $identifier, string $password, ?string $intendedPath = null): InteractiveMemberLoginResult
    {
        $result = $this->credentials->verifyForInteractiveLogin($identifier, $password);

        if (! $result->authenticated || $result->user === null) {
            return InteractiveMemberLoginResult::failure($result->rateLimited);
        }

        $destination = $this->destinationFor($result->user, $intendedPath);
        if ($destination === null) {
            return InteractiveMemberLoginResult::failure();
        }

        return $result->user->must_change_password
            ? InteractiveMemberLoginResult::passwordChangeRequired($result->user)
            : InteractiveMemberLoginResult::normal($result->user, $destination);
    }

    public function destinationFor(User $user, ?string $intendedPath = null): ?string
    {
        $member = $this->members->resolveForUserId((string) $user->getAuthIdentifier());
        $memberEligible = $member !== null && $this->members->isEligibleAdult($member);
        $operatorEligible = $this->operators->canAccess($user);

        if (! $memberEligible && ! $operatorEligible) {
            return null;
        }

        if ($operatorEligible && $memberEligible) {
            $intended = $this->authorizedIntendedPath($intendedPath);
            if ($intended !== null) {
                return $intended;
            }
        }

        return $memberEligible
            ? ($this->members->isComplete($member) ? '/member/dashboard' : '/member/profile')
            : '/operator';
    }

    private function authorizedIntendedPath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return $path === '/operator' || str_starts_with($path, '/operator/') || $path === '/member/dashboard' || str_starts_with($path, '/member/')
            ? $path
            : null;
    }
}
