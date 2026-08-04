<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

use App\Shared\Events\DomainEvent;
use App\Shared\Time\Clock;
use Illuminate\Support\Facades\DB;

final class DatabaseOutboxStore implements OutboxStore
{
    public function __construct(private readonly Clock $clock) {}

    public function record(DomainEvent $event): void
    {
        $now = $this->clock->now();
        $data = $event->toArray();

        DB::table('outbox_messages')->insert([
            'event_id' => $data['event_id'],
            'event_name' => $data['event_name'],
            'event_version' => $data['event_version'],
            'payload' => json_encode($data['payload'], JSON_THROW_ON_ERROR),
            'occurred_at' => $event->occurredAt(),
            'subject_id' => $data['subject_id'],
            'correlation_id' => $data['correlation_id'],
            'available_at' => $now,
            'status' => 'pending',
            'attempts' => 0,
            'published_at' => null,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function find(string $eventId): ?array
    {
        $row = DB::table('outbox_messages')->where('event_id', $eventId)->first();

        return $row === null ? null : (array) $row;
    }

    public function markPublished(string $eventId): void
    {
        DB::table('outbox_messages')
            ->where('event_id', $eventId)
            ->update([
                'status' => 'published',
                'published_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
    }
}
