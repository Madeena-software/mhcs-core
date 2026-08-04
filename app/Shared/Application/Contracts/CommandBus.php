<?php

declare(strict_types=1);

namespace App\Shared\Application\Contracts;

interface CommandBus
{
    /**
     * @param  class-string<Command>  $command
     * @param  class-string<CommandHandler>  $handler
     */
    public function register(string $command, string $handler): void;

    public function dispatch(Command $command): mixed;
}
