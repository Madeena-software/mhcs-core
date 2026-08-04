<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use App\Shared\Context\AuthenticatedContext;
use App\Shared\Identity\LocalId;
use App\Shared\Security\SensitiveDataSanitizer;
use DateTimeImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AuditEvent
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $eventId,
        public int $eventVersion,
        public ?LocalId $actorId,
        public ?LocalId $sessionId,
        public array $roles,
        public array $permissions,
        public ?LocalId $siteId,
        public ?LocalId $caseId,
        public ?string $targetType,
        public ?string $targetId,
        public string $action,
        public ?string $previousStateDigest,
        public ?string $newStateDigest,
        public ?string $reason,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $recordedAt,
        public ?string $correlationId,
        public string $source,
        public string $outcome,
        public array $metadata,
    ) {
        if ($this->eventId === '' || $this->eventVersion < 1 || trim($this->action) === '' || trim($this->source) === '') {
            throw new InvalidArgumentException('Audit events require an ID, version, action, and source.');
        }

        SensitiveDataSanitizer::assertSafeString($this->reason);
        SensitiveDataSanitizer::assertSafeString($this->targetId);

        foreach ([$this->previousStateDigest, $this->newStateDigest] as $digest) {
            if ($digest !== null && preg_match('/\A[0-9a-f]{64}\z/i', $digest) !== 1) {
                throw new InvalidArgumentException('Audit state values must be SHA-256 digests.');
            }
        }

        SensitiveDataSanitizer::assertSafe($this->metadata);
    }

    public static function fromContext(
        AuthenticatedContext $context,
        string $action,
        string $source,
        string $outcome,
        DateTimeImmutable $occurredAt,
        ?string $targetType = null,
        ?string $targetId = null,
        ?string $reason = null,
        ?string $previousStateDigest = null,
        ?string $newStateDigest = null,
        array $metadata = [],
    ): self {
        return new self(
            eventId: (string) Str::uuid(),
            eventVersion: 1,
            actorId: $context->actorId,
            sessionId: $context->sessionId,
            roles: $context->roles,
            permissions: $context->permissions,
            siteId: $context->siteId,
            caseId: $context->caseId,
            targetType: $targetType,
            targetId: $targetId,
            action: $action,
            previousStateDigest: $previousStateDigest,
            newStateDigest: $newStateDigest,
            reason: $reason,
            occurredAt: $occurredAt,
            recordedAt: $occurredAt,
            correlationId: $context->operationId === null ? null : (string) $context->operationId,
            source: $source,
            outcome: $outcome,
            metadata: $metadata,
        );
    }
}
