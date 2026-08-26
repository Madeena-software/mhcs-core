<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Enums;

enum RegistrationSource: string
{
    case Online = 'online';
    case WalkIn = 'walk_in';
    case Administrator = 'administrator';
    case NonclinicalValidation = 'nonclinical_validation';
}
