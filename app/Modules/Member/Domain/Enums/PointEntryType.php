<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Enums;

enum PointEntryType: string
{
    case Credit = 'credit';
    case Charge = 'charge';
}
