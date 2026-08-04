<?php

declare(strict_types=1);

namespace App\Shared\Context;

final class NullAuthenticatedContextProvider implements AuthenticatedContextProvider
{
    public function current(): AuthenticatedContext
    {
        return AuthenticatedContext::anonymous();
    }
}
