<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Models\User;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Context\AuthenticatedContextProvider;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final class CredentialVerifier
{
    private string $dummyHash;

    public function __construct(
        private readonly ProtectedIdentifierService $identifiers,
        private readonly AuditStore $audit,
        private readonly AuthenticatedContextProvider $context,
        private readonly Clock $clock,
    ) {
        $this->dummyHash = Hash::make(bin2hex(random_bytes(24)));
    }

    public function verify(string $identifier, string $password): CredentialVerificationResult
    {
        $identifier = $this->canonicalIdentifier($identifier);
        $key = 'credential:'.$this->identifiers->lookupDigest($identifier);
        $maxAttempts = (int) config('mhcs.security.login.max_attempts', 5);
        $decaySeconds = (int) config('mhcs.security.login.decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->recordFailure($key, 'rate_limited');

            return CredentialVerificationResult::failure(true);
        }

        $user = $this->findUser($identifier);
        $hash = $user?->password ?? $this->dummyHash;
        $validPassword = Hash::check($password, $hash);

        RateLimiter::hit($key, $decaySeconds);

        if (
            $user === null
            || ! $validPassword
            || $user->isSuspended()
            || $user->must_change_password
        ) {
            $this->recordFailure($key, $user === null ? 'unknown' : 'rejected');

            return CredentialVerificationResult::failure();
        }

        RateLimiter::clear($key);
        $this->audit->append(AuditEvent::fromContext(
            $this->context->current(),
            action: 'credential.verify',
            source: 'auth',
            outcome: 'success',
            occurredAt: $this->clock->now(),
            targetType: User::class,
            targetId: (string) $user->getAuthIdentifier(),
            metadata: ['rate_key_digest' => hash('sha256', $key)],
        ));

        return CredentialVerificationResult::success($user);
    }

    private function findUser(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', strtolower(trim($identifier)))->first();
        }

        return User::query()
            ->where('identifier_digest', $this->identifiers->lookupDigest($identifier))
            ->first();
    }

    private function canonicalIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        return filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : $identifier;
    }

    private function recordFailure(string $key, string $reason): void
    {
        $this->audit->append(AuditEvent::fromContext(
            $this->context->current(),
            action: 'credential.verify',
            source: 'auth',
            outcome: 'failure',
            occurredAt: $this->clock->now(),
            metadata: [
                'reason' => $reason,
                'rate_key_digest' => hash('sha256', $key),
            ],
        ));
    }
}
