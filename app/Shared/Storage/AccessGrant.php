<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Security\KeyMaterial;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class AccessGrant
{
    private function __construct(
        private array $claims,
        private string $signature,
    ) {}

    public static function issue(
        string $target,
        string $actorId,
        string $audience,
        string $purpose,
        DateTimeImmutable $issuedAt,
        DateTimeImmutable $expiresAt,
        string $correlationId,
        KeyMaterial $key,
    ): self {
        if (
            trim($target) === ''
            || trim($actorId) === ''
            || trim($audience) === ''
            || trim($purpose) === ''
            || trim($correlationId) === ''
            || $expiresAt <= $issuedAt
        ) {
            throw new InvalidArgumentException('Access grants require complete, ordered claims.');
        }

        $claims = [
            'target' => $target,
            'actor_id' => $actorId,
            'audience' => $audience,
            'purpose' => $purpose,
            'issued_at' => $issuedAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'expires_at' => $expiresAt->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'correlation_id' => $correlationId,
        ];

        return new self($claims, $key->digest(self::canonical($claims)));
    }

    /** @param array<string, mixed> $claims */
    public static function fromArray(array $claims, string $signature): self
    {
        $required = ['target', 'actor_id', 'audience', 'purpose', 'issued_at', 'expires_at', 'correlation_id'];

        foreach ($required as $key) {
            if (! isset($claims[$key]) || ! is_string($claims[$key]) || trim($claims[$key]) === '') {
                throw new ObjectAccessException('Malformed access grant.');
            }
        }

        if (array_diff(array_keys($claims), $required) !== []) {
            throw new ObjectAccessException('Malformed access grant claims.');
        }

        if (! is_string($signature) || $signature === '') {
            throw new ObjectAccessException('Malformed access grant signature.');
        }

        return new self($claims, $signature);
    }

    public function verify(
        AuthenticatedContext $context,
        string $audience,
        string $purpose,
        string $target,
        DateTimeImmutable $now,
        KeyMaterial $key,
    ): void {
        if (
            $context->actorId === null
            || $context->operationId === null
            || $context->purpose !== $purpose
            || (string) $context->actorId !== $this->claims['actor_id']
            || $this->claims['audience'] !== $audience
            || $this->claims['purpose'] !== $purpose
            || $this->claims['target'] !== $target
            || $this->claims['correlation_id'] !== (string) $context->operationId
        ) {
            throw new ObjectAccessException('Access grant claims do not authorize this request.');
        }

        try {
            $issuedAt = new DateTimeImmutable($this->claims['issued_at']);
            $expiresAt = new DateTimeImmutable($this->claims['expires_at']);
        } catch (\Exception $exception) {
            throw new ObjectAccessException('Access grant timestamps are malformed.', previous: $exception);
        }

        if ($expiresAt <= $issuedAt || $now < $issuedAt || $now >= $expiresAt) {
            throw new ObjectAccessException('Access grant is expired or not yet valid.');
        }

        if (! hash_equals($key->digest(self::canonical($this->claims)), $this->signature)) {
            throw new ObjectAccessException('Access grant signature is invalid.');
        }
    }

    public function target(): string
    {
        return $this->claims['target'];
    }

    /** @return array<string, string> */
    public function claims(): array
    {
        return $this->claims;
    }

    /** @param array<string, string> $claims */
    private static function canonical(array $claims): string
    {
        return json_encode([
            'target' => $claims['target'],
            'actor_id' => $claims['actor_id'],
            'audience' => $claims['audience'],
            'purpose' => $claims['purpose'],
            'issued_at' => $claims['issued_at'],
            'expires_at' => $claims['expires_at'],
            'correlation_id' => $claims['correlation_id'],
        ], JSON_THROW_ON_ERROR);
    }
}
