<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Models\User;

interface AuthorizationClaimResolver
{
    /** @return list<string> */
    public function roles(User|string $user): array;

    /** @return list<string> */
    public function permissions(User|string $user): array;
}
