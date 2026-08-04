<?php

declare(strict_types=1);

namespace App\Shared\Identity;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final readonly class ExternalId implements JsonSerializable, Stringable
{
    public function __construct(
        public string $source,
        public string $value,
    ) {
        if (trim($this->source) === '' || trim($this->value) === '') {
            throw new InvalidArgumentException('An external identifier needs a source and value.');
        }
    }

    public function jsonSerialize(): array
    {
        return ['source' => $this->source, 'value' => $this->value];
    }

    public function __toString(): string
    {
        return $this->source.':'.$this->value;
    }
}
