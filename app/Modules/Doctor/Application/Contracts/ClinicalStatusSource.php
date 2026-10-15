<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Contracts;

interface ClinicalStatusSource
{
    /**
     * Trusted adapter only. The caller holds the case guard row lock until commit.
     * All relevant reporting writers must acquire that same lock before updates.
     * External sources without equivalent coordination must return null.
     * Evidence must identify the complete current reporting-source inventory.
     */
    public function readWhileLocked(string $case, string $binding, string $handoff): ?array;
}
