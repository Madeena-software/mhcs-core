<?php

declare(strict_types=1);

namespace App\Shared\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

final class AccountStateUserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if ($user instanceof User && ! $user->canAuthenticate()) {
            return false;
        }

        return parent::validateCredentials($user, $credentials);
    }
}
