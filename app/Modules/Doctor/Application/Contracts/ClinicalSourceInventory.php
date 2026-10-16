<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Contracts;

interface ClinicalSourceInventory
{
    /** Positive, independently established closed source inventory; unknown coverage throws. */
    public function assertClosed(string $case, string $handoff, string $binding, string $report): void;
}
