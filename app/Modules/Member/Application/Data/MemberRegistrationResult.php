<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

final readonly class MemberRegistrationResult
{
    public function __construct(
        public string $memberId,
        public string $userId,
        public string $medicalRecordNumber,
        public string $accountStatus,
        public string $identityStatus,
        public bool $replayed = false,
    ) {}
}
