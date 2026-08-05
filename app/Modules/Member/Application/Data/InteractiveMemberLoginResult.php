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
        public ?string $destination,
    ) {}

    public static function normal(User $user, string $destination): self
    {
        return new self(InteractiveLoginState::NormalMemberSession, $user, false, $destination);
    }

    public static function passwordChangeRequired(User $user): self
    {
        return new self(InteractiveLoginState::PasswordChangeRequired, $user, false, null);
    }

    public static function failure(bool $rateLimited = false): self
    {
        return new self(InteractiveLoginState::Failure, null, $rateLimited, null);
    }
}
