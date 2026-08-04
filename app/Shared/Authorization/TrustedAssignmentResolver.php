<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Shared\Context\AuthenticatedContext;

interface TrustedAssignmentResolver
{
    public function resolve(AuthenticatedContext $context): ?AssignmentEvidence;
}
