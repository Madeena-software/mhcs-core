<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Shared\Time\Clock;
use DateTimeImmutable;
use Throwable;

final readonly class OperatorArrivalConfirmationService
{
    public const SESSION_KEY = 'operator.arrival_confirmation';

    public function __construct(private Clock $clock) {}

    /** @param array<string, mixed> $state */
    public function store(array $state): void
    {
        session()->put(self::SESSION_KEY, $state);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** @return array{status: string, state?: array<string, mixed>} */
    public function inspect(string $profileId, string $siteId, ?string $token = null): array
    {
        $state = session()->get(self::SESSION_KEY);
        if ($state === null) {
            return ['status' => 'absent'];
        }
        if (! is_array($state) || ! $this->isStructurallyValid($state)) {
            $this->clear();

            return ['status' => 'malformed'];
        }
        $state['consumed'] = $state['consumed'] ?? false;

        if ($token !== null && ! hash_equals((string) $state['token'], trim($token))) {
            return ['status' => 'token-mismatch'];
        }
        if ($state['operator_profile_id'] !== trim($profileId) || $state['operator_site_id'] !== trim($siteId)) {
            $this->clear();

            return ['status' => 'stale-context'];
        }

        try {
            $expiresAt = new DateTimeImmutable($state['expires_at']);
        } catch (Throwable) {
            $this->clear();

            return ['status' => 'malformed'];
        }
        if ($this->clock->now() >= $expiresAt) {
            $this->clear();

            return ['status' => 'expired'];
        }

        if ($state['consumed']) {
            return ['status' => 'consumed', 'state' => $state];
        }

        return ['status' => 'active', 'state' => $state];
    }

    /** @param array<string, mixed> $state */
    private function isStructurallyValid(array $state): bool
    {
        foreach (['token', 'booking_id', 'occurrence_at', 'idempotency_key', 'operator_profile_id', 'operator_site_id', 'schedule_id', 'expires_at'] as $field) {
            if (! is_string($state[$field] ?? null) || trim($state[$field]) === '') {
                return false;
            }
        }

        return (! array_key_exists('consumed', $state) || is_bool($state['consumed']))
            && (! ($state['consumed'] ?? false) || is_array($state['result'] ?? null));
    }
}
