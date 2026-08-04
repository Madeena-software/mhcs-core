<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use App\Models\User;

final readonly class InteractiveMemberLoginResult
{
    private function __construct(
        public InteractiveLoginState $state,
        public ?User $user,
        public bool $rateLimited,
    ) {}

    public static function normal(User $user): self
    {
        return new self(InteractiveLoginState::NormalMemberSession, $user, false);
    }

    public static function passwordChangeRequired(User $user): self
    {
        return new self(InteractiveLoginState::PasswordChangeRequired, $user, false);
    }

    public static function failure(bool $rateLimited = false): self
    {
        return new self(InteractiveLoginState::Failure, null, $rateLimited);
    }
}
