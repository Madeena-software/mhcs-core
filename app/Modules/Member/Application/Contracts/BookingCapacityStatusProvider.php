<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface BookingCapacityStatusProvider
{
    /** @return list<string> */
    public function participatingStatuses(): array;
}
