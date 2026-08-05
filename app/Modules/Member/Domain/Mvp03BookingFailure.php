<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class Mvp03BookingFailure extends RuntimeException
{
    /** @var list<string> */
    public const CATEGORIES = [
        'member_unavailable',
        'member_ineligible',
        'active_booking_exists',
        'schedule_unavailable',
        'capacity_full',
        'price_changed',
        'rate_unavailable',
        'insufficient_personal_points',
        'idempotency_conflict',
        'unexpected_failure',
    ];

    public function __construct(public readonly string $category, string $message, ?Throwable $previous = null)
    {
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('The booking failure category is not controlled.');
        }

        parent::__construct($message, previous: $previous);
    }
}
