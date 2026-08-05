<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface TrustedOperatorSiteContextResolver
{
    public function matches(AuthenticatedContext $context, string $operatorSiteId, string $permission): bool;
}
