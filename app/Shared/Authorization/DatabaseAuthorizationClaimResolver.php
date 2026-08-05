<?php

declare(strict_types=1);

namespace App\Shared\Authorization;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseAuthorizationClaimResolver implements AuthorizationClaimResolver
{
    /** @var array<string, list<string>> */
    private array $rolesByUser = [];

    /** @var array<string, list<string>> */
    private array $permissionsByUser = [];

    public function roles(User|string $user): array
    {
        return $this->rolesByUser[$this->userId($user)] ??= $this->resolve(
            'authorization_role_assignments',
            'role',
            $this->userId($user),
        );
    }

    public function permissions(User|string $user): array
    {
        return $this->permissionsByUser[$this->userId($user)] ??= $this->resolve(
            'authorization_permission_assignments',
            'permission',
            $this->userId($user),
        );
    }

    /** @return list<string> */
    private function resolve(string $table, string $column, string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        try {
            $claims = DB::table($table)
                ->where('user_id', $userId)
                ->where('active', true)
                ->orderBy($column)
                ->pluck($column)
                ->all();
        } catch (Throwable) {
            return [];
        }

        $claims = array_values(array_filter($claims, static function (mixed $claim): bool {
            return is_string($claim)
                && trim($claim) !== ''
                && trim($claim) === $claim
                && ! str_contains($claim, '*');
        }));

        return array_values(array_unique($claims));
    }

    private function userId(User|string $user): string
    {
        return trim($user instanceof User ? (string) $user->getAuthIdentifier() : $user);
    }
}
