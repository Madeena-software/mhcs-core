<?php

declare(strict_types=1);

namespace App\Shared\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface ContextAwareQueryHandler extends QueryHandler
{
    public function handleWithContext(Query $query, AuthenticatedContext $context): mixed;

    public function handle(Query $query): mixed;
}
