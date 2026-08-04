<?php

declare(strict_types=1);

namespace App\Shared\Events;

use App\Shared\Context\CorrelationId;
use App\Shared\Identity\LocalId;
use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final readonly class VersionedDomainEvent implements DomainEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private LocalId $id,
        private string $name,
        private int $version,
        private DateTimeImmutable $time,
        private array $data,
        private ?LocalId $subject = null,
        private ?CorrelationId $correlation = null,
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('An event name cannot be empty.');
        }

        if ($this->version < 1) {
            throw new InvalidArgumentException('An event version must be positive.');
        }

        try {
            json_encode($this->data, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Event payload must be JSON serializable.', previous: $exception);
        }
    }

    public function eventId(): string
    {
        return (string) $this->id;
    }

    public function eventName(): string
    {
        return $this->name;
    }

    public function eventVersion(): int
    {
        return $this->version;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->time;
    }

    public function subjectId(): ?string
    {
        return $this->subject === null ? null : (string) $this->subject;
    }

    public function correlationId(): ?string
    {
        return $this->correlation === null ? null : (string) $this->correlation;
    }

    public function payload(): array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId(),
            'event_name' => $this->eventName(),
            'event_version' => $this->eventVersion(),
            'occurred_at' => $this->occurredAt()->format(DATE_ATOM),
            'subject_id' => $this->subjectId(),
            'correlation_id' => $this->correlationId(),
            'payload' => $this->payload(),
        ];
    }
}
