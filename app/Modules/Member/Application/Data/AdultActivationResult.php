<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

final readonly class AdultActivationResult
{
    public function __construct(
        public string $memberId,
        public string $userId,
        public string $accountStatus,
        public bool $mustChangePassword,
        public bool $replayed = false,
    ) {}
}
