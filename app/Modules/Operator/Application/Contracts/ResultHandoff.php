<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Contracts;

interface ResultHandoff
{
    /** Locks and verifies the exact current technical handoff; returns its recorded subject identity. */
    public function subject(string $case, string $handoff, string $binding, string $site, string $study): array;
}
