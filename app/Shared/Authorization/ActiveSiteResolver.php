<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Identity\LocalId;

interface ActiveSiteResolver
{
    public function resolve(AuthenticatedContext $context): ?LocalId;
}
