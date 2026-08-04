<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\MemberIdentityException;
use App\Shared\Authorization\AuthorizationGuard;
use App\Shared\Context\AuthenticatedContext;
use Illuminate\Support\Facades\DB;

final readonly class MemberAuthorization
{
    public const REGISTRATION_PERMISSION = 'member.registration.manage';

    public const IDENTITY_VERIFICATION_PERMISSION = 'member.identity.verify';

    public const ASSET_ACCESS_PERMISSION = 'member.asset.read';

    public const GUARDIAN_MANAGEMENT_PERMISSION = 'member.guardian.manage';

    public const ACCOUNT_STATE_PERMISSION = 'member.account.manage';

    public const ASSISTED_RECOVERY_PERMISSION = 'member.assisted-recovery';

    public const AGE_TRANSITION_PERMISSION = 'member.age-transition';

    public function __construct(private AuthorizationGuard $guard) {}

    public function context(string $purpose): AuthenticatedContext
    {
        try {
            return $this->guard->current($purpose);
        } catch (\Throwable $exception) {
            throw new MemberIdentityException('A trusted Member authorization context is required.', previous: $exception);
        }
    }

    public function registration(): AuthenticatedContext
    {
        return $this->requireAdministrator('member.registration', self::REGISTRATION_PERMISSION);
    }

    public function identityVerification(): AuthenticatedContext
    {
        return $this->requireAdministrator('member.identity.verify', self::IDENTITY_VERIFICATION_PERMISSION);
    }

    public function guardianManagement(): AuthenticatedContext
    {
        return $this->requireAdministrator('member.guardian.manage', self::GUARDIAN_MANAGEMENT_PERMISSION);
    }

    public function accountState(): AuthenticatedContext
    {
        return $this->requireAdministrator('member.account-state', self::ACCOUNT_STATE_PERMISSION);
    }

    public function assistedRecovery(): AuthenticatedContext
    {
        return $this->requireAdministrator('member.assisted-recovery', self::ASSISTED_RECOVERY_PERMISSION);
    }

    public function ageTransition(): AuthenticatedContext
    {
        return $this->requireAdministrator('member.age-transition', self::AGE_TRANSITION_PERMISSION);
    }

    public function hasAdministratorPermission(AuthenticatedContext $context, string $permission): bool
    {
        return in_array('administrator', $context->roles, true)
            && in_array($permission, $context->permissions, true);
    }

    public function hasPermission(AuthenticatedContext $context, string $permission): bool
    {
        return in_array($permission, $context->permissions, true);
    }

    public function assetAccess(string $memberId, string $purpose = 'member.asset.read'): AuthenticatedContext
    {
        if ($purpose !== 'member.asset.read') {
            throw new MemberIdentityException('The verification asset purpose is not supported.');
        }

        $context = $this->context($purpose);
        if ($this->hasAdministratorPermission($context, self::ASSET_ACCESS_PERMISSION)) {
            return $context;
        }

        $actorId = (string) $context->actorId;
        $owner = DB::table('members')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('members.id', $memberId)
            ->where('users.id', $actorId)
            ->where('users.account_status', 'active')
            ->where('users.login_enabled', true)
            ->where('users.must_change_password', false)
            ->exists();

        $guardian = DB::table('member_guardians')
            ->join('members as guardians', 'guardians.id', '=', 'member_guardians.guardian_member_id')
            ->join('users', 'users.id', '=', 'guardians.user_id')
            ->where('member_guardians.child_member_id', $memberId)
            ->where('member_guardians.status', 'verified')
            ->whereNull('member_guardians.ends_at')
            ->where('guardians.identity_status', 'verified')
            ->where('users.id', $actorId)
            ->where('users.account_status', 'active')
            ->where('users.login_enabled', true)
            ->where('users.must_change_password', false)
            ->exists();

        if (! $owner && ! $guardian) {
            throw new MemberIdentityException('Verification asset authorization is required.');
        }

        return $context;
    }

    private function requireAdministrator(string $purpose, string $permission): AuthenticatedContext
    {
        $context = $this->context($purpose);

        if (! $this->hasAdministratorPermission($context, $permission)) {
            throw new MemberIdentityException('Administrator authorization is required.');
        }

        return $context;
    }
}
