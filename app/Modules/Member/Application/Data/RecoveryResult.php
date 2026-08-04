<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

final readonly class RecoveryResult
{
    public function __construct(
        public string $memberId,
        public string $userId,
        public string $accountStatus,
        public ?string $temporaryCredential,
        public bool $replayed = false,
    ) {}
}
