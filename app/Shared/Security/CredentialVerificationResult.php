<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Models\User;

final readonly class CredentialVerificationResult
{
    private function __construct(
        public bool $authenticated,
        public string $message,
        public ?User $user,
        public bool $rateLimited,
    ) {}

    public static function success(User $user): self
    {
        return new self(true, 'Authenticated.', $user, false);
    }

    public static function failure(bool $rateLimited = false): self
    {
        return new self(false, 'The credentials are invalid.', null, $rateLimited);
    }
}
