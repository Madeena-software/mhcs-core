<?php

declare(strict_types=1);

namespace App\Shared\Context;

interface AuthenticatedContextProvider
{
    public function current(): AuthenticatedContext;
}
