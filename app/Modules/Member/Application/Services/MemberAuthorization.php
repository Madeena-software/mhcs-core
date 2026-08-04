<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Modules\Member\Domain\MemberIdentityException;
use App\Shared\Authorization\AuthorizationGuard;
use App\Shared\Context\AuthenticatedContext;

final readonly class MemberAuthorization
{
    public function __construct(private AuthorizationGuard $guard) {}

    public function context(string $purpose): AuthenticatedContext
    {
        try {
            return $this->guard->current($purpose);
        } catch (\Throwable $exception) {
            throw new MemberIdentityException('A trusted Member authorization context is required.', previous: $exception);
        }
    }

    public function administrator(string $purpose): AuthenticatedContext
    {
        $context = $this->context($purpose);

        if (! $this->isAdministrator($context)) {
            throw new MemberIdentityException('Administrator authorization is required.');
        }

        return $context;
    }

    public function isAdministrator(AuthenticatedContext $context): bool
    {
        return in_array('administrator', $context->roles, true)
            || in_array('member.identity.manage', $context->permissions, true)
            || in_array('member.identity.verify', $context->permissions, true)
            || in_array('member.account.manage', $context->permissions, true);
    }
}
