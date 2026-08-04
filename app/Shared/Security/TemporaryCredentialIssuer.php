<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class TemporaryCredentialIssuer
{
    public function issue(User $user): string
    {
        $plaintext = bin2hex(random_bytes(32));

        $user->forceFill([
            'password' => Hash::make($plaintext),
            'must_change_password' => true,
        ])->save();

        return $plaintext;
    }

    public function replace(User $user, string $password): void
    {
        if (trim($password) === '') {
            throw new InvalidArgumentException('A replacement password is required.');
        }

        $user->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => false,
        ])->save();
    }

    public function invalidate(User $user): void
    {
        $user->forceFill([
            'password' => Hash::make(bin2hex(random_bytes(32))),
            'must_change_password' => true,
        ])->save();
    }
}
