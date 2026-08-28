<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface OperatorMemberRegistrationContract
{
    /** @return array<string, mixed> */
    public function registerWalkIn(
        AuthenticatedContext $context,
        string $operatorSiteId,
        string $name,
        ?string $email,
        ?string $phone,
        string $operationId,
    ): array;

    /** @return list<array<string, mixed>> */
    public function searchMembers(AuthenticatedContext $context, string $operatorSiteId, string $query): array;
}
