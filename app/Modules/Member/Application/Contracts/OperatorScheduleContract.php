<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface OperatorScheduleContract
{
    /** @return array{site: array<string, mixed>, schedules: list<array<string, mixed>>} */
    public function schedules(AuthenticatedContext $context, string $operatorSiteId): array;

    /** @return array{site: array<string, mixed>, services: list<array<string, mixed>>} */
    public function createForm(AuthenticatedContext $context, string $operatorSiteId): array;

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    public function createSchedule(AuthenticatedContext $context, string $operatorSiteId, array $attributes): array;

    /** @return array<string, mixed> */
    public function showSchedule(AuthenticatedContext $context, string $operatorSiteId, string $scheduleId, ?string $query = null): array;
}
