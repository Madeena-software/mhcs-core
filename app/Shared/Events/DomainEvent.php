<?php

declare(strict_types=1);

namespace App\Shared\Events;

use DateTimeImmutable;

interface DomainEvent
{
    public function eventId(): string;

    public function eventName(): string;

    public function eventVersion(): int;

    public function occurredAt(): DateTimeImmutable;

    public function subjectId(): ?string;

    public function correlationId(): ?string;

    /** @return array<string, mixed> */
    public function payload(): array;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
