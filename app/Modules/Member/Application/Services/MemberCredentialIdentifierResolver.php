<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Services;

use App\Models\User;
use App\Shared\Security\CredentialIdentifierResolver;
use App\Shared\Security\ProtectedIdentifierService;

final readonly class MemberCredentialIdentifierResolver implements CredentialIdentifierResolver
{
    public function __construct(private ProtectedIdentifierService $identifiers) {}

    public function resolve(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', strtolower($identifier))->first();
        }

        $digest = $this->identifiers->lookupDigest($identifier);

        return User::query()
            ->join('members', 'members.user_id', '=', 'users.id')
            ->where('members.nik_lookup_digest', $digest)
            ->select('users.*')
            ->first();
    }
}
