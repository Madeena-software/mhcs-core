<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface NonclinicalValidationIdentityContract
{
    public function isExactNonclinicalValidationMember(string $memberId): bool;
}
