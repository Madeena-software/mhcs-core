<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Events\DomainEvent;

interface OutboxStore
{
    /**
     * Record this event in the caller's current transaction.
     */
    public function record(DomainEvent $event): void;

    /** @return array<string, mixed>|null */
    public function find(string $eventId): ?array;

    public function markPublished(string $eventId): void;
}
