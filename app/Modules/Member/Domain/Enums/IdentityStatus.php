<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Enums;

enum IdentityStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case NonclinicalValidation = 'nonclinical_validation';
}
