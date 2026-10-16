<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

interface RadiologistCaseIdentity
{
    public function resolveClinical(string $workspace, int $generation): array;

    public function forAssignment(string $handoff): array;

    public function service(string $booking, string $schedule, string $site): string;
}
