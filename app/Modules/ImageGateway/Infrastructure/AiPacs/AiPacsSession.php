<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure\AiPacs;

use DateTimeImmutable;
use JsonSerializable;

final readonly class AiPacsSession implements JsonSerializable
{
    /**
     * @param array<string, string> $cookies
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public ?string $token = null,
        public array $cookies = [],
        public ?DateTimeImmutable $expiresAt = null,
        public array $attributes = [],
    ) {}

    public function isAuthenticated(): bool
    {
        return $this->token !== null || $this->cookies !== [];
    }

    /**
     * Prevent leaking sensitive session tokens or cookies when serialized.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'has_token' => $this->token !== null,
            'cookie_names' => array_keys($this->cookies),
            'expires_at' => $this->expiresAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
