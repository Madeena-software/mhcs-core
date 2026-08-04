<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use App\Shared\Application\Contracts\ContextAwareQueryHandler;
use App\Shared\Application\Contracts\Query;
use App\Shared\Application\Contracts\QueryBus;
use App\Shared\Application\Contracts\QueryHandler;
use App\Shared\Application\Exceptions\DuplicateHandler;
use App\Shared\Application\Exceptions\InvalidHandler;
use App\Shared\Application\Exceptions\MissingHandler;
use App\Shared\Context\AuthenticatedContextProvider;
use Illuminate\Contracts\Container\Container;

final class InProcessQueryBus implements QueryBus
{
    /** @var array<class-string<Query>, class-string<QueryHandler>> */
    private array $handlers = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<Query>  $query
     * @param  class-string<QueryHandler>  $handler
     */
    public function register(string $query, string $handler): void
    {
        if (isset($this->handlers[$query])) {
            throw new DuplicateHandler("A query handler is already registered for {$query}.");
        }

        if (! is_a($query, Query::class, true) || ! is_a($handler, QueryHandler::class, true)) {
            throw new InvalidHandler('Query registrations must use the query and handler contracts.');
        }

        $this->handlers[$query] = $handler;
    }

    public function dispatch(Query $query): mixed
    {
        $queryType = $query::class;
        $handlerType = $this->handlers[$queryType] ?? null;

        if ($handlerType === null) {
            throw new MissingHandler("No query handler is registered for {$queryType}.");
        }

        $handler = $this->container->make($handlerType);

        if ($handler instanceof ContextAwareQueryHandler) {
            return $handler->handleWithContext(
                $query,
                $this->container->make(AuthenticatedContextProvider::class)->current(),
            );
        }

        return $handler->handle($query);
    }
}
