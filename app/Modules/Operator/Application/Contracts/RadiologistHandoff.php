<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Contracts;

interface RadiologistHandoff
{
    /** Independently authorizes the managing actor and locks the current upstream handoff. */
    /** Read-only observation under the Doctor guard; does not acquire an upstream lock. */
    public function matches(string $case, string $handoff, string $binding): bool;

    public function forStudy(string $study): ?string;

    public function protocol(string $workspace, int $generation): ?object;

    public function selection(string $handoff): object;
}
