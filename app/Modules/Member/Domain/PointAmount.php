<?php

declare(strict_types=1);

namespace App\Modules\Member\Domain;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/** Four-decimal point arithmetic using integer-scaled decimal values. */
final readonly class PointAmount implements JsonSerializable, Stringable
{
    private const SCALE = 4;

    private function __construct(private string $scaled)
    {
        if (! preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $this->scaled)) {
            throw new InvalidArgumentException('Point amount is not normalized.');
        }
    }

    public static function zero(): self
    {
        return new self('0');
    }

    public static function fromString(string $amount): self
    {
        $amount = trim($amount);
        if (preg_match('/\A(-?)([0-9]+)(?:\.([0-9]{1,4}))?\z/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException('Point amounts require up to four decimal places.');
        }

        $whole = ltrim($matches[2], '0') ?: '0';
        $fraction = str_pad($matches[3] ?? '', self::SCALE, '0');
        $scaled = ltrim($whole.$fraction, '0') ?: '0';

        return new self(($matches[1] === '-' && $scaled !== '0' ? '-' : '').$scaled);
    }

    public function add(self $other): self
    {
        return new self(self::addSigned($this->scaled, $other->scaled));
    }

    public function subtract(self $other): self
    {
        return new self(self::addSigned($this->scaled, self::negate($other->scaled)));
    }

    public function compare(self $other): int
    {
        $leftNegative = str_starts_with($this->scaled, '-');
        $rightNegative = str_starts_with($other->scaled, '-');

        if ($leftNegative !== $rightNegative) {
            return $leftNegative ? -1 : 1;
        }

        $left = $leftNegative ? substr($this->scaled, 1) : $this->scaled;
        $right = $rightNegative ? substr($other->scaled, 1) : $other->scaled;
        $comparison = self::compareAbs($left, $right);

        return $leftNegative ? -$comparison : $comparison;
    }

    public function isNegative(): bool
    {
        return str_starts_with($this->scaled, '-');
    }

    public function isPositive(): bool
    {
        return ! $this->isNegative() && $this->scaled !== '0';
    }

    public function jsonSerialize(): string
    {
        return (string) $this;
    }

    public function __toString(): string
    {
        $negative = str_starts_with($this->scaled, '-');
        $digits = $negative ? substr($this->scaled, 1) : $this->scaled;
        $digits = str_pad($digits, self::SCALE + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -self::SCALE);
        $fraction = substr($digits, -self::SCALE);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private static function addSigned(string $left, string $right): string
    {
        $leftNegative = str_starts_with($left, '-');
        $rightNegative = str_starts_with($right, '-');
        $leftAbs = $leftNegative ? substr($left, 1) : $left;
        $rightAbs = $rightNegative ? substr($right, 1) : $right;

        if ($leftNegative === $rightNegative) {
            $sum = self::addAbs($leftAbs, $rightAbs);

            return $leftNegative && $sum !== '0' ? '-'.$sum : $sum;
        }

        $comparison = self::compareAbs($leftAbs, $rightAbs);
        if ($comparison === 0) {
            return '0';
        }

        $sum = $comparison > 0
            ? self::subtractAbs($leftAbs, $rightAbs)
            : self::subtractAbs($rightAbs, $leftAbs);
        $negative = $comparison > 0 ? $leftNegative : $rightNegative;

        return $negative ? '-'.$sum : $sum;
    }

    private static function negate(string $value): string
    {
        return $value === '0' ? '0' : (str_starts_with($value, '-') ? substr($value, 1) : '-'.$value);
    }

    private static function addAbs(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        $left = strrev($left);
        $right = strrev($right);
        $length = max(strlen($left), strlen($right));

        for ($index = 0; $index < $length; $index++) {
            $sum = (int) ($left[$index] ?? 0) + (int) ($right[$index] ?? 0) + $carry;
            $result .= (string) ($sum % 10);
            $carry = intdiv($sum, 10);
        }

        return strrev($result.($carry > 0 ? $carry : ''));
    }

    private static function subtractAbs(string $left, string $right): string
    {
        $borrow = 0;
        $result = '';
        $left = strrev($left);
        $right = strrev($right);

        for ($index = 0, $length = strlen($left); $index < $length; $index++) {
            $difference = (int) $left[$index] - (int) ($right[$index] ?? 0) - $borrow;
            if ($difference < 0) {
                $difference += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result .= (string) $difference;
        }

        return ltrim(strrev($result), '0') ?: '0';
    }

    private static function compareAbs(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        for ($index = 0, $length = strlen($left); $index < $length; $index++) {
            if ($left[$index] !== $right[$index]) {
                return $left[$index] < $right[$index] ? -1 : 1;
            }
        }

        return 0;
    }
}
