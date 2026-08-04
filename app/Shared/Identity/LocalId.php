<?php

declare(strict_types=1);

namespace App\Shared\Identity;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class LocalId implements JsonSerializable, Stringable
{
    private function __construct(public string $value)
    {
        if ($this->value === '') {
            throw new InvalidArgumentException('A local identifier cannot be empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self(trim($value));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
