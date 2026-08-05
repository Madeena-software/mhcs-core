<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Enums;

enum ScheduleStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
