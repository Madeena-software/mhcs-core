<?php

declare(strict_types=1);

namespace App\Shared\Money;

use InvalidArgumentException;
use JsonSerializable;

final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $minorAmount,
        string $currency,
    ) {
        $currency = strtoupper(trim($currency));

        if (! preg_match('/\A[A-Z]{3}\z/', $currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter code.');
        }

        $this->currency = $currency;
    }

    public readonly string $currency;

    public static function fromMinorUnits(int $minorAmount, string $currency): self
    {
        return new self($minorAmount, $currency);
    }

    public static function fromFloat(float $amount, string $currency): never
    {
        throw new InvalidArgumentException('Money cannot be constructed from a floating-point amount.');
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorAmount + $other->minorAmount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorAmount - $other->minorAmount, $this->currency);
    }

    public function jsonSerialize(): array
    {
        return ['minor_amount' => $this->minorAmount, 'currency' => $this->currency];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money arithmetic requires matching currencies.');
        }
    }
}
