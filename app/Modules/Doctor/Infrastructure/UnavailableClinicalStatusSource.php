<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Infrastructure;

use App\Modules\Doctor\Application\Contracts\ClinicalStatusSource;

final class UnavailableClinicalStatusSource implements ClinicalStatusSource
{
    public function readWhileLocked(string $case, string $binding, string $handoff): ?array
    {
        return null;
    }
}
