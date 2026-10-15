<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Contracts;

interface PendingRadiographerHandoff
{
    /** Trusted consumer version check, inside its transaction before acquiring the Doctor guard lock. */
    public function isCurrent(string $handoff, string $binding): bool;
}
