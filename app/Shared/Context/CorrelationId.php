<?php

declare(strict_types=1);

namespace App\Shared\Context;

use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class CorrelationId implements JsonSerializable, Stringable
{
    public function __construct(public string $value)
    {
        if (trim($this->value) === '') {
            throw new InvalidArgumentException('A correlation identifier cannot be empty.');
        }
    }

    public static function random(): self
    {
        return new self((string) Str::uuid());
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
