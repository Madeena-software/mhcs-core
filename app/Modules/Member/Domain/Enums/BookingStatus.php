<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case CheckedIn = 'checked_in';
    case InProgress = 'in_progress';
    case Postponed = 'postponed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
}
