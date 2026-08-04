<?php

declare(strict_types=1);

namespace App\Shared\Time;

use DateTimeImmutable;
use DateTimeZone;

final class FrozenClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $time) {}

    public function now(): DateTimeImmutable
    {
        return $this->time->setTimezone(new DateTimeZone('UTC'));
    }
}
