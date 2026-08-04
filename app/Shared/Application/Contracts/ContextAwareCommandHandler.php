<?php

declare(strict_types=1);

namespace App\Shared\Application\Contracts;

use App\Shared\Context\AuthenticatedContext;

interface ContextAwareCommandHandler extends CommandHandler
{
    public function handleWithContext(Command $command, AuthenticatedContext $context): mixed;

    public function handle(Command $command): mixed;
}
