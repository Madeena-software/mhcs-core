<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

use DateTimeImmutable;

final readonly class GuardianAuthorizationResult
{
    public function __construct(
        public string $actingGuardianMemberId,
        public string $dependentMemberId,
        public string $purpose,
        public DateTimeImmutable $authorizedAt,
    ) {}
}
