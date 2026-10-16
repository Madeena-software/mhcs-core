<?php

declare(strict_types=1);

namespace App\Modules\Doctor\Application\Contracts;

interface FinalReport
{
    /** Routing references only; caller must authorize before requesting content. */
    public function locate(string $case): array;

    /** Caller holds sorted participating accounts; takes Operator then Doctor locks. */
    public function readWhileLocked(string $case, string $report): array;
}
