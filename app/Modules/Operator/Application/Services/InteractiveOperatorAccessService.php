<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Models\User;
use App\Modules\Member\Application\Contracts\InteractiveOperatorAccessResolver;
use App\Modules\Operator\Domain\Models\OperatorProfile;
use App\Shared\Authorization\AuthorizationClaimResolver;

final readonly class InteractiveOperatorAccessService implements InteractiveOperatorAccessResolver
{
    public function __construct(private AuthorizationClaimResolver $claims) {}

    public function canAccess(User $user): bool
    {
        if ($user->account_status !== 'active' || ! ($user->login_enabled ?? false)) {
            return false;
        }

        return in_array(OperatorAuthorization::ROLE, $this->claims->roles($user), true)
            && in_array(OperatorAuthorization::PORTAL_ACCESS, $this->claims->permissions($user), true)
            && OperatorProfile::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('active', true)
                ->exists();
    }
}
