<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Models\User;

interface CredentialIdentifierResolver
{
    public function resolve(string $identifier): ?User;
}
