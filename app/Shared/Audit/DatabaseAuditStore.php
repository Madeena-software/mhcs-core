<?php

declare(strict_types=1);

namespace App\Shared\Audit;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use JsonException;

final class DatabaseAuditStore implements AuditStore
{
    public function append(AuditEvent $event): void
    {
        if (DB::table('audit_events')->where('event_id', $event->eventId)->exists()) {
            throw new AuditException('Audit event IDs are append-only and unique.');
        }

        try {
            DB::table('audit_events')->insert([
                'event_id' => $event->eventId,
                'event_version' => $event->eventVersion,
                'actor_id' => $event->actorId === null ? null : (string) $event->actorId,
                'session_id' => $event->sessionId === null ? null : (string) $event->sessionId,
                'roles' => json_encode($event->roles, JSON_THROW_ON_ERROR),
                'permissions' => json_encode($event->permissions, JSON_THROW_ON_ERROR),
                'site_id' => $event->siteId === null ? null : (string) $event->siteId,
                'case_id' => $event->caseId === null ? null : (string) $event->caseId,
                'target_type' => $event->targetType,
                'target_id' => $event->targetId,
                'action' => $event->action,
                'previous_state_digest' => $event->previousStateDigest,
                'new_state_digest' => $event->newStateDigest,
                'reason' => $event->reason,
                'occurred_at' => $event->occurredAt,
                'recorded_at' => $event->recordedAt,
                'correlation_id' => $event->correlationId,
                'source' => $event->source,
                'outcome' => $event->outcome,
                'metadata' => json_encode($event->metadata, JSON_THROW_ON_ERROR),
                'created_at' => $event->recordedAt,
                'updated_at' => $event->recordedAt,
            ]);
        } catch (QueryException|JsonException $exception) {
            throw new AuditException('Audit event could not be appended.', previous: $exception);
        }
    }
}
