<?php

declare(strict_types=1);

namespace App\Shared\Application\Contracts;

interface QueryBus
{
    /**
     * @param  class-string<Query>  $query
     * @param  class-string<QueryHandler>  $handler
     */
    public function register(string $query, string $handler): void;

    public function dispatch(Query $query): mixed;
}
