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
        $throttling = $this->throttlingConfiguration();
        $identifierDigest = $this->identifiers->lookupDigest($identifier);
        $origin = $this->requestOrigin();
        $originDigest = hash('sha256', $origin);
        $pairKey = 'credential:pair:'.hash('sha256', $identifierDigest.'|'.$originDigest);
        $originKey = 'credential:origin:'.$originDigest;
        $identifierKey = 'credential:identifier:'.$identifierDigest;

        if (
            RateLimiter::tooManyAttempts($pairKey, $throttling['pair_max_attempts'])
            || RateLimiter::tooManyAttempts($originKey, $throttling['origin_max_attempts'])
            || RateLimiter::tooManyAttempts($identifierKey, $throttling['identifier_max_attempts'])
        ) {
            $this->recordFailure($pairKey, 'rate_limited');

            return CredentialVerificationResult::failure(true);
        }

        $user = $this->findUser($identifier);
        $hash = $user?->password ?? $this->dummyHash;
        $validPassword = Hash::check($password, $hash);

        if (
            $user === null
            || ! $validPassword
            || $user->isSuspended()
            || $user->must_change_password
        ) {
            RateLimiter::hit($pairKey, $throttling['decay_seconds']);
            RateLimiter::hit($originKey, $throttling['decay_seconds']);
            RateLimiter::hit($identifierKey, $throttling['decay_seconds']);
            $this->recordFailure($pairKey, $user === null ? 'unknown' : 'rejected');

            return CredentialVerificationResult::failure();
        }

        RateLimiter::clear($pairKey);
        RateLimiter::clear($identifierKey);
        $this->audit->append(AuditEvent::fromContext(
            $this->context->current(),
            action: 'credential.verify',
            source: 'auth',
            outcome: 'success',
            occurredAt: $this->clock->now(),
            targetType: User::class,
            targetId: (string) $user->getAuthIdentifier(),
            metadata: ['rate_key_digest' => hash('sha256', $pairKey)],
        ));

        return CredentialVerificationResult::success($user);
    }

    /** @return array{pair_max_attempts: int, origin_max_attempts: int, identifier_max_attempts: int, decay_seconds: int} */
    private function throttlingConfiguration(): array
    {
        $configuration = config('mhcs.security.login');
        if (! is_array($configuration)) {
            throw new SecurityException('Credential throttling configuration is missing.');
        }

        $pairMaxAttempts = $this->positiveInteger($configuration['pair_max_attempts'] ?? null, 'pair_max_attempts');
        $originMaxAttempts = $this->positiveInteger($configuration['origin_max_attempts'] ?? null, 'origin_max_attempts');
        $identifierMaxAttempts = $this->positiveInteger($configuration['identifier_max_attempts'] ?? null, 'identifier_max_attempts');
        $decaySeconds = $this->positiveInteger($configuration['decay_seconds'] ?? null, 'decay_seconds');

        if ($identifierMaxAttempts <= $pairMaxAttempts || $identifierMaxAttempts <= $originMaxAttempts) {
            throw new SecurityException('Credential identifier throttling must be broader than pair and origin throttling.');
        }

        return [
            'pair_max_attempts' => $pairMaxAttempts,
            'origin_max_attempts' => $originMaxAttempts,
            'identifier_max_attempts' => $identifierMaxAttempts,
            'decay_seconds' => $decaySeconds,
        ];
    }

    private function positiveInteger(mixed $value, string $name): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (! is_string($value) || ! preg_match('/^[1-9][0-9]*$/D', $value)) {
            throw new SecurityException("Credential throttling [{$name}] must be a positive integer.");
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            throw new SecurityException("Credential throttling [{$name}] must be a positive integer.");
        }

        return $parsed;
    }

    private function requestOrigin(): string
    {
        if (! app()->bound('request')) {
            return 'unknown';
        }

        $origin = request()->server('REMOTE_ADDR');

        return is_string($origin) && trim($origin) !== '' ? $origin : 'unknown';
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
