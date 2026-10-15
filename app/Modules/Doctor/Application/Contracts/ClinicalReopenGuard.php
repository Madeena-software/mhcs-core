<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Contracts;

interface ClinicalReopenGuard
{
    public function initialize(string $case): void;

    /** Called in the same transaction as reopen, under the workspace lock. */
    public function permitReopen(string $case, string $binding, string $handoff): void;
}
