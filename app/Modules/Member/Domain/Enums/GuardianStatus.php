<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Enums;

enum GuardianStatus: string
{
    case Verified = 'verified';
    case Ended = 'ended';
}
