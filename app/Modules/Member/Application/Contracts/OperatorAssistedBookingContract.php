<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface OperatorAssistedBookingContract
{
    /** @return array<string, mixed> */
    public function bookForOperator(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $scheduleId,
        string $memberId,
        string $operationId,
    ): array;
}
