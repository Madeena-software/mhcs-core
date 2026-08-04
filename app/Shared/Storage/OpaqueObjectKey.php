<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use InvalidArgumentException;

final readonly class OpaqueObjectKey
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        if (
            $value === ''
            || str_contains($value, '..')
            || str_contains($value, '\\')
            || str_starts_with($value, '.')
            || str_starts_with($value, '/')
            || str_ends_with($value, '/')
            || str_contains($value, '//')
        ) {
            throw new InvalidArgumentException('Object keys must be opaque relative values.');
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
