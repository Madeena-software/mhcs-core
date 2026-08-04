<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use App\Shared\Application\Contracts\Command;
use App\Shared\Application\Contracts\CommandBus;
use App\Shared\Application\Contracts\CommandHandler;
use App\Shared\Application\Contracts\ContextAwareCommandHandler;
use App\Shared\Application\Exceptions\DuplicateHandler;
use App\Shared\Application\Exceptions\InvalidHandler;
use App\Shared\Application\Exceptions\MissingHandler;
use App\Shared\Context\AuthenticatedContextProvider;
use Illuminate\Contracts\Container\Container;

final class InProcessCommandBus implements CommandBus
{
    /** @var array<class-string<Command>, class-string<CommandHandler>> */
    private array $handlers = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<Command>  $command
     * @param  class-string<CommandHandler>  $handler
     */
    public function register(string $command, string $handler): void
    {
        if (isset($this->handlers[$command])) {
            throw new DuplicateHandler("A command handler is already registered for {$command}.");
        }

        if (! is_a($command, Command::class, true) || ! is_a($handler, CommandHandler::class, true)) {
            throw new InvalidHandler('Command registrations must use the command and handler contracts.');
        }

        $this->handlers[$command] = $handler;
    }

    public function dispatch(Command $command): mixed
    {
        $commandType = $command::class;
        $handlerType = $this->handlers[$commandType] ?? null;

        if ($handlerType === null) {
            throw new MissingHandler("No command handler is registered for {$commandType}.");
        }

        $handler = $this->container->make($handlerType);

        if ($handler instanceof ContextAwareCommandHandler) {
            return $handler->handleWithContext(
                $command,
                $this->container->make(AuthenticatedContextProvider::class)->current(),
            );
        }

        return $handler->handle($command);
    }
}
