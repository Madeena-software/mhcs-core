<?php

declare(strict_types=1);

namespace App\Shared\Storage;

use DateTimeImmutable;

final readonly class PrivateObject
{
    public function __construct(
        public OpaqueObjectKey $key,
        public string $checksum,
        public int $bytes,
        public string $encryption,
        public DateTimeImmutable $createdAt,
    ) {}
}
