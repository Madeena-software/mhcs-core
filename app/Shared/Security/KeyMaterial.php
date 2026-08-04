<?php

declare(strict_types=1);

namespace App\Shared\Security;

use InvalidArgumentException;

final readonly class KeyMaterial
{
    private function __construct(public string $value) {}

    public static function from(string $value): self
    {
        if (trim($value) === '' || strlen($value) < 16) {
            throw new SecurityException('Required key material is missing or too short.');
        }

        return new self($value);
    }

    public static function fromConfig(mixed $value): self
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException('Key material must be supplied as a string.');
        }

        return self::from($value);
    }

    public function encryptionKey(): string
    {
        return hash('sha256', $this->value, true);
    }

    public function digest(string $value): string
    {
        return hash_hmac('sha256', $value, $this->value);
    }
}
