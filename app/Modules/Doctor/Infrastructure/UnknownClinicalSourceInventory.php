<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Infrastructure;

use App\Modules\Doctor\Application\Contracts\ClinicalSourceInventory;
use DomainException;

final class UnknownClinicalSourceInventory implements ClinicalSourceInventory
{
    public function assertClosed(string $case, string $handoff, string $binding, string $report): void
    {
        // Internal consistency never establishes historical/external source coverage.
        throw new DomainException('result_unavailable');
    }
}
