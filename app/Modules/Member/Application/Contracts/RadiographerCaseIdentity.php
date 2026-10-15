<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface RadiographerCaseIdentity
{
    /** Independently authorizes the exact assignment generation and audits disclosure. */
    public function resolve(string $workspace, int $generation): array;
}
